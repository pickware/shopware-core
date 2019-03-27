<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Store\Exception\CanNotDownloadPluginManagedByComposerException;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreInvalidCredentialsException;
use Shopware\Core\Framework\Store\Exception\StoreNotAvailableException;
use Shopware\Core\Framework\Store\Exception\StoreTokenMissingException;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\StoreSettingsEntity;
use Shopware\Core\System\User\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StoreController extends AbstractController
{
    /**
     * @var StoreClient
     */
    private $storeClient;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var PluginManagementService
     */
    private $pluginManagementService;

    /**
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    /**
     * @var EntityRepositoryInterface
     */
    private $userRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $storeSettingsRepo;

    public function __construct(
        StoreClient $storeClient,
        EntityRepositoryInterface $pluginRepo,
        PluginManagementService $pluginManagementService,
        PluginLifecycleService $pluginLifecycleService,
        EntityRepositoryInterface $userRepository,
        EntityRepositoryInterface $storeSettingsRepo
    ) {
        $this->storeClient = $storeClient;
        $this->pluginRepo = $pluginRepo;
        $this->pluginManagementService = $pluginManagementService;
        $this->pluginLifecycleService = $pluginLifecycleService;
        $this->userRepository = $userRepository;
        $this->storeSettingsRepo = $storeSettingsRepo;
    }

    /**
     * @Route("/api/v{version}/_action/store/ping", name="api.custom.store.ping", methods={"GET"})
     */
    public function pingStoreAPI(): Response
    {
        try {
            $this->storeClient->ping();
        } catch (ClientException $exception) {
            throw new StoreNotAvailableException();
        }

        return new Response();
    }

    /**
     * @Route("/api/v{version}/_action/store/login", name="api.custom.store.login", methods={"POST"})
     */
    public function login(Request $request, Context $context): JsonResponse
    {
        $shopwareId = $request->request->get('shopwareId');
        $password = $request->request->get('password');

        if ($shopwareId === null || $password === null) {
            throw new StoreInvalidCredentialsException();
        }

        $userId = $context->getSourceContext()->getUserId();
        try {
            $accessTokenStruct = $this->storeClient->loginWithShopwareId($shopwareId, $password, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', 'shopSecret'));

        /** @var StoreSettingsEntity|null $shopSecret */
        $shopSecret = $this->storeSettingsRepo->search($criteria, $context)->first();

        $data = [
            [
                'id' => $shopSecret !== null ? $shopSecret->getId() : null,
                'key' => 'shopSecret',
                'value' => $accessTokenStruct->getShopSecret(),
            ],
        ];
        $this->storeSettingsRepo->upsert($data, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', 'shopwareId'));

        /** @var StoreSettingsEntity|null $shopSecret */
        $shopSecret = $this->storeSettingsRepo->search($criteria, $context)->first();

        $data = [
            [
                'id' => $shopSecret !== null ? $shopSecret->getId() : null,
                'key' => 'shopwareId',
                'value' => $shopwareId,
            ],
        ];
        $this->storeSettingsRepo->upsert($data, $context);

        $this->userRepository->update([
            ['id' => $userId, 'storeToken' => $accessTokenStruct->getShopUserToken()->getToken()],
        ], $context);

        return new JsonResponse();
    }

    /**
     * @Route("/api/v{version}/_action/store/licenses", name="api.custom.store.licenses", methods={"GET"})
     */
    public function getLicenseList(Request $request, Context $context): JsonResponse
    {
        $storeToken = $this->getUserStoreToken($context);

        try {
            $licenseList = $this->storeClient->getLicenseList($storeToken, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $licenseList,
            'total' => count($licenseList),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/store/updates", name="api.custom.store.updates", methods={"GET"})
     */
    public function getUpdateList(Context $context): JsonResponse
    {
        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search(new Criteria(), $context)->getEntities();
        try {
            $storeToken = $this->getUserStoreToken($context);
        } catch (StoreTokenMissingException $e) {
            $storeToken = null;
        }

        try {
            $updatesList = $this->storeClient->getUpdatesList($storeToken, $plugins, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $updatesList,
            'total' => count($updatesList),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/store/download", name="api.custom.store.download", methods={"GET"})
     */
    public function downloadPlugin(Request $request, Context $context): JsonResponse
    {
        $pluginName = $request->query->get('pluginName');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', $pluginName));

        /** @var PluginEntity|null $plugin */
        $plugin = $this->pluginRepo->search($criteria, $context)->first();

        if ($plugin !== null && $plugin->isManagedByComposer()) {
            throw new CanNotDownloadPluginManagedByComposerException('can not downloads plugins managed by composer from store api');
        }

        $storeToken = $this->getUserStoreToken($context);

        try {
            $data = $this->storeClient->getDownloadDataForPlugin($pluginName, $storeToken, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        $statusCode = $this->pluginManagementService->downloadStorePlugin($data->getLocation(), $context);
        if ($statusCode !== Response::HTTP_OK) {
            return new JsonResponse([], $statusCode);
        }

        if ($plugin->getUpgradeVersion()) {
            $this->pluginLifecycleService->updatePlugin($plugin, $context);
        }

        return new JsonResponse();
    }

    private function getUserStoreToken(Context $context): string
    {
        $userId = $context->getSourceContext()->getUserId();

        /** @var UserEntity|null $user */
        $user = $this->userRepository->search(new Criteria([$userId]), $context)->first();

        if ($user->getStoreToken() === null) {
            throw new StoreTokenMissingException('the user does not have a store token');
        }

        return $user->getStoreToken();
    }
}

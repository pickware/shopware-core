<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\BillingStreetRule;

class BillingStreetRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => 'example street']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setStreet('example street');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testCaseInsensitive(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => 'ExaMple StreEt']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setStreet('example street');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testNotMatch(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => 'example street']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setStreet('test street');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithoutAddress(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => '.ExaMple StreEt']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context
            ->method('getCustomer')
            ->willReturn(null);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }
}

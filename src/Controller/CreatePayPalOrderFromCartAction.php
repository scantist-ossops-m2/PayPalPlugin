<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use Payum\Core\Payum;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CreatePayPalOrderFromCartAction
{
    /** @var Payum */
    private $payum;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var FactoryInterface */
    private $stateMachineFactory;

    /** @var ObjectManager */
    private $paymentManager;

    /** @var OrderProviderInterface */
    private $orderProvider;

    /** @var CapturePaymentResolverInterface */
    private $capturePaymentResolver;

    public function __construct(
        Payum $payum,
        OrderRepositoryInterface $orderRepository,
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        OrderProviderInterface $orderProvider,
        CapturePaymentResolverInterface $capturePaymentResolver
    ) {
        $this->payum = $payum;
        $this->orderRepository = $orderRepository;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentManager = $paymentManager;
        $this->orderProvider = $orderProvider;
        $this->capturePaymentResolver = $capturePaymentResolver;
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->attributes->getInt('id');
        $order = $this->orderProvider->provideOrderById($id);

        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

        $this->capturePaymentResolver->resolve($payment);

        $this->paymentManager->flush();

        return new JsonResponse([
            'id' => $order->getId(),
            'orderID' => $payment->getDetails()['paypal_order_id'],
            'status' => $payment->getState(),
        ]);
    }
}
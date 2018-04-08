<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.shop and write us
 * an email on mikolaj.krol@bitbag.pl.
 */

declare(strict_types=1);

namespace BitBag\SyliusMolliePlugin\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpPostRedirect;
use Payum\Core\Request\Capture;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use Payum\Core\Security\GenericTokenFactoryInterface;
use Payum\Core\Security\TokenInterface;

final class CaptureAction implements ActionInterface, ApiAwareInterface, GenericTokenFactoryAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * @var \Mollie_API_Client
     */
    private $mollieApiClient;

    /**
     * @var GenericTokenFactoryInterface
     */
    private $tokenFactory;

    /**
     * {@inheritDoc}
     */
    public function setApi($mollieApiClient): void
    {
        if (false === $mollieApiClient instanceof \Mollie_API_Client) {
            throw new UnsupportedApiException('Not supported.Expected an instance of '. \Mollie_API_Client::class);
        }

        $this->mollieApiClient = $mollieApiClient;
    }

    /**
     * @param GenericTokenFactoryInterface $genericTokenFactory
     *
     * @return void
     */
    public function setGenericTokenFactory(GenericTokenFactoryInterface $genericTokenFactory = null): void
    {
        $this->tokenFactory = $genericTokenFactory;
    }

    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var ArrayObject $details */
        $details = $request->getModel();

        if (true === isset($details['id'])) {
            return;
        }

        /** @var TokenInterface $token */
        $token = $request->getToken();

        $notifyToken = $this->tokenFactory->createNotifyToken($token->getGatewayName(), $token->getDetails());

        $details['redirectUrl'] = $token->getTargetUrl();
        $details['webhookUrl'] = str_replace('http://127.0.0.1:8000/', 'https://e820fac0.ngrok.io/', $notifyToken->getTargetUrl());

        $payment = $this->mollieApiClient->payments->create($details->toUnsafeArray());

        $details['id'] = $payment->id;

        throw new HttpPostRedirect($payment->getPaymentUrl());
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}

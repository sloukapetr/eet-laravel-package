<?php

declare(strict_types=1);

namespace SloukaPetr\Eet;

use DOMDocument;
use DOMElement;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use Throwable;

final class ResponseVerifier
{
    public function passes(string $xml, string $environment): bool
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($loaded !== true) {
            return false;
        }

        $certificate = $this->embeddedCertificate($document);

        if ($certificate === null || ! $this->signatureIsValid($document, $certificate)) {
            return false;
        }

        return $this->chainsToEnvironment($certificate, $environment);
    }

    private function signatureIsValid(DOMDocument $document, string $certificate): bool
    {
        try {
            $signature = new XMLSecurityDSig;
            $node = $signature->locateSignature($document);

            if (! $node instanceof DOMElement) {
                return false;
            }

            $signature->idKeys[] = 'wsu:Id';
            $signature->idNS = ['wsu' => SoapNamespaces::WSU];
            $signature->canonicalizeSignedInfo();

            if ($signature->validateReference() !== true) {
                return false;
            }

            $key = $signature->locateKey();

            if ($key === null) {
                return false;
            }

            $key->loadKey($certificate, false, true);

            return $signature->verify($key) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    private function embeddedCertificate(DOMDocument $document): ?string
    {
        $tokens = $document->getElementsByTagNameNS(SoapNamespaces::WSSE, 'BinarySecurityToken');
        $token = $tokens->item(0);

        if (! $token instanceof DOMElement) {
            return null;
        }

        $body = preg_replace('/\s+/', '', $token->textContent) ?? '';

        if ($body === '' || base64_decode($body, true) === false) {
            return null;
        }

        return "-----BEGIN CERTIFICATE-----\n".trim(chunk_split($body, 64, "\n"))."\n-----END CERTIFICATE-----\n";
    }

    private function chainsToEnvironment(string $certificate, string $environment): bool
    {
        if (! in_array($environment, ['playground', 'production'], true)) {
            return false;
        }

        $directory = dirname(__DIR__).'/resources/ca/'.$environment;

        foreach (['ca_eet-sub_cert-playground.crt', 'ca_eet-sub_prod.pem', 'ca_eet-root_cert-playground.crt', 'ca_eet-root_prod.pem'] as $name) {
            $path = $directory.'/'.$name;

            if (! is_file($path)) {
                continue;
            }

            $authority = file_get_contents($path);

            if (! is_string($authority) || $authority === '') {
                continue;
            }

            if (openssl_x509_verify($certificate, $authority) === 1) {
                return true;
            }
        }

        return false;
    }
}

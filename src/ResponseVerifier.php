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

    /**
     * The access PDFs name the response signer as a certificate issued by
     * I.CA Public CA/RSA 06/2022. The numbers 100001063 and 100000002 are the
     * serial numbers of that subordinate CA and of I.CA Root CA/RSA 05/2022.
     */
    private function chainsToEnvironment(string $certificate, string $environment): bool
    {
        if (! in_array($environment, ['playground', 'production'], true)) {
            return false;
        }

        $leaf = openssl_x509_parse($certificate);
        $issuer = is_array($leaf) ? ($leaf['issuer']['CN'] ?? null) : null;

        if ($issuer !== 'I.CA Public CA/RSA 06/2022') {
            return false;
        }

        $directory = dirname(__DIR__).'/resources/ca/'.$environment;
        $subordinate = $this->authority($directory.'/ica-public-ca-rsa-06-2022.pem', '100001063', 'I.CA Public CA/RSA 06/2022');
        $root = $this->authority($directory.'/ica-root-ca-rsa-05-2022.pem', '100000002', 'I.CA Root CA/RSA 05/2022');

        if ($subordinate === null || $root === null) {
            return false;
        }

        return openssl_x509_verify($certificate, $subordinate) === 1
            && openssl_x509_verify($subordinate, $root) === 1;
    }

    private function authority(string $path, string $serial, string $commonName): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $pem = file_get_contents($path);
        $parsed = is_string($pem) ? openssl_x509_parse($pem) : false;

        if (! is_string($pem) || $pem === '' || ! is_array($parsed)) {
            return null;
        }

        $subject = $parsed['subject']['CN'] ?? null;
        $found = $parsed['serialNumber'] ?? null;

        if ($subject !== $commonName || $found !== $serial) {
            return null;
        }

        return $pem;
    }
}

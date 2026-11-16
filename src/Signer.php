<?php

declare(strict_types=1);

namespace SloukaPetr\Eet;

use DOMDocument;
use DOMElement;
use DOMNode;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SloukaPetr\Eet\Exceptions\CertificateExpired;
use SloukaPetr\Eet\Exceptions\InvalidCertificate;
use Throwable;

final class Signer
{
    public function sign(DOMDocument $document, Certificate $certificate): string
    {
        $body = $this->requiredElement($document, SoapNamespaces::SOAP, 'Body');
        $bodyId = 'id-'.bin2hex(random_bytes(16));
        $body->setAttributeNS(SoapNamespaces::WSU, 'wsu:Id', $bodyId);

        $digest = base64_encode(hash('sha256', $this->canonical($body, ['v4']), true));
        $tokenId = 'X509-'.bin2hex(random_bytes(16));

        $header = $this->requiredElement($document, SoapNamespaces::SOAP, 'Header');
        $security = $document->createElementNS(SoapNamespaces::WSSE, 'wsse:Security');
        $header->appendChild($security);

        $encodedCertificate = XMLSecurityDSig::get509XCert($certificate->certificatePem());

        if ($encodedCertificate === '') {
            throw new InvalidCertificate('Receipt could not be signed.');
        }

        $token = $document->createElementNS(
            SoapNamespaces::WSSE,
            'wsse:BinarySecurityToken',
            $encodedCertificate,
        );
        $token->setAttribute('EncodingType', SoapNamespaces::BASE64_ENCODING);
        $token->setAttribute('ValueType', SoapNamespaces::X509_VALUE_TYPE);
        $token->setAttributeNS(SoapNamespaces::WSU, 'wsu:Id', $tokenId);
        $security->appendChild($token);

        $signature = $this->signatureElement($document, $bodyId, $digest, $tokenId);
        $security->appendChild($signature);

        $signedInfo = $signature->getElementsByTagNameNS(SoapNamespaces::DS, 'SignedInfo')->item(0);
        $signatureValue = $signature->getElementsByTagNameNS(SoapNamespaces::DS, 'SignatureValue')->item(0);

        if (! $signedInfo instanceof DOMElement || ! $signatureValue instanceof DOMElement) {
            throw new InvalidCertificate('Receipt could not be signed.');
        }

        try {
            $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
            $key->loadKey($certificate->privateKey());
            $signed = $key->signData($this->canonical($signedInfo, ['soapenv', 'v4']));
        } catch (CertificateExpired $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new InvalidCertificate('Receipt could not be signed.');
        }

        if (! is_string($signed) || $signed === '') {
            throw new InvalidCertificate('Receipt could not be signed.');
        }

        $signatureValue->textContent = base64_encode($signed);

        $xml = $document->saveXML($document->documentElement);

        if (! is_string($xml) || $xml === '') {
            throw new InvalidCertificate('Receipt could not be signed.');
        }

        return $xml;
    }

    private function signatureElement(DOMDocument $document, string $bodyId, string $digest, string $tokenId): DOMElement
    {
        $signature = $document->createElementNS(SoapNamespaces::DS, 'ds:Signature');
        $signedInfo = $document->createElementNS(SoapNamespaces::DS, 'ds:SignedInfo');
        $signature->appendChild($signedInfo);

        $canonicalization = $document->createElementNS(SoapNamespaces::DS, 'ds:CanonicalizationMethod');
        $canonicalization->setAttribute('Algorithm', SoapNamespaces::EXC_C14N);
        $canonicalization->appendChild($this->inclusiveNamespaces($document, 'soapenv v4'));
        $signedInfo->appendChild($canonicalization);

        $method = $document->createElementNS(SoapNamespaces::DS, 'ds:SignatureMethod');
        $method->setAttribute('Algorithm', SoapNamespaces::SIGNATURE_METHOD);
        $signedInfo->appendChild($method);

        $reference = $document->createElementNS(SoapNamespaces::DS, 'ds:Reference');
        $reference->setAttribute('URI', '#'.$bodyId);
        $signedInfo->appendChild($reference);

        $transforms = $document->createElementNS(SoapNamespaces::DS, 'ds:Transforms');
        $reference->appendChild($transforms);
        $transform = $document->createElementNS(SoapNamespaces::DS, 'ds:Transform');
        $transform->setAttribute('Algorithm', SoapNamespaces::EXC_C14N);
        $transform->appendChild($this->inclusiveNamespaces($document, 'v4'));
        $transforms->appendChild($transform);

        $digestMethod = $document->createElementNS(SoapNamespaces::DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', SoapNamespaces::DIGEST);
        $reference->appendChild($digestMethod);
        $reference->appendChild($document->createElementNS(SoapNamespaces::DS, 'ds:DigestValue', $digest));

        $signature->appendChild($document->createElementNS(SoapNamespaces::DS, 'ds:SignatureValue'));

        $keyInfo = $document->createElementNS(SoapNamespaces::DS, 'ds:KeyInfo');
        $signature->appendChild($keyInfo);
        $referenceToken = $document->createElementNS(SoapNamespaces::WSSE, 'wsse:SecurityTokenReference');
        $keyInfo->appendChild($referenceToken);
        $keyReference = $document->createElementNS(SoapNamespaces::WSSE, 'wsse:Reference');
        $keyReference->setAttribute('URI', '#'.$tokenId);
        $keyReference->setAttribute('ValueType', SoapNamespaces::X509_VALUE_TYPE);
        $referenceToken->appendChild($keyReference);

        return $signature;
    }

    private function inclusiveNamespaces(DOMDocument $document, string $prefixList): DOMElement
    {
        $node = $document->createElementNS(SoapNamespaces::EXC_C14N, 'ec:InclusiveNamespaces');
        $node->setAttribute('PrefixList', $prefixList);

        return $node;
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function canonical(DOMNode $node, array $prefixes): string
    {
        $canonical = $node->C14N(true, false, null, $prefixes);

        if (! is_string($canonical) || $canonical === '') {
            throw new InvalidCertificate('Receipt could not be signed.');
        }

        return $canonical;
    }

    private function requiredElement(DOMDocument $document, string $namespace, string $localName): DOMElement
    {
        $node = $document->getElementsByTagNameNS($namespace, $localName)->item(0);

        if (! $node instanceof DOMElement) {
            throw new InvalidCertificate('Receipt could not be signed.');
        }

        return $node;
    }
}

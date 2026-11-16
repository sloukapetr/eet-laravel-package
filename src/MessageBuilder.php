<?php

declare(strict_types=1);

namespace SloukaPetr\Eet;

use Carbon\CarbonImmutable;
use DOMDocument;
use SloukaPetr\Eet\Data\Receipt;
use SloukaPetr\Eet\Data\SendOptions;
use SloukaPetr\Eet\Support\AmountFormatter;
use SloukaPetr\Eet\Support\DateTimeFormatter;

final class MessageBuilder
{
    public function build(Receipt $receipt, SendOptions $options, string $uuid, CarbonImmutable $sentAt): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = false;

        $envelope = $document->createElementNS(SoapNamespaces::SOAP, 'soapenv:Envelope');
        $document->appendChild($envelope);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:v4', SoapNamespaces::EET);

        $header = $document->createElementNS(SoapNamespaces::SOAP, 'soapenv:Header');
        $body = $document->createElementNS(SoapNamespaces::SOAP, 'soapenv:Body');
        $envelope->appendChild($header);
        $envelope->appendChild($body);

        $trzba = $document->createElementNS(SoapNamespaces::EET, 'v4:Trzba');
        $body->appendChild($trzba);

        $headerAttributes = [
            'dat_odesl' => DateTimeFormatter::format($sentAt, 'datOdesl'),
            'overeni' => $options->verification ? 'true' : 'false',
            'prvni_zaslani' => $options->firstSend ? 'true' : 'false',
            'uuid_zpravy' => $uuid,
        ];
        $hlavicka = $document->createElementNS(SoapNamespaces::EET, 'v4:Hlavicka');
        foreach ($headerAttributes as $name => $value) {
            $hlavicka->setAttribute($name, $value);
        }
        $trzba->appendChild($hlavicka);

        $dataAttributes = [
            'celk_trzba' => AmountFormatter::format($receipt->totalMinor, 'totalMinor'),
        ];

        if ($receipt->prepaidDrawMinor !== null) {
            $dataAttributes['cerp_zuct'] = AmountFormatter::format($receipt->prepaidDrawMinor, 'prepaidDrawMinor');
        }

        $dataAttributes['dat_trzby'] = DateTimeFormatter::format($receipt->soldAt, 'soldAt');
        $dataAttributes['eic_popl'] = $receipt->eic;

        if ($receipt->principalEic !== null) {
            $dataAttributes['eic_poverujiciho'] = $receipt->principalEic;
        }

        $dataAttributes['id_jednotky'] = (string) $receipt->unitId;
        $dataAttributes['id_pokl'] = $receipt->deviceId;
        $dataAttributes['porad_cis'] = $receipt->serial;

        if ($receipt->multipleTaxpayers === true) {
            $dataAttributes['povereni_vice_popl'] = 'true';
        }

        if ($receipt->prepaidTopUpMinor !== null) {
            $dataAttributes['urceno_cerp_zuct'] = AmountFormatter::format($receipt->prepaidTopUpMinor, 'prepaidTopUpMinor');
        }

        $data = $document->createElementNS(SoapNamespaces::EET, 'v4:Data');
        foreach ($dataAttributes as $name => $value) {
            $data->setAttribute($name, $value);
        }
        $trzba->appendChild($data);

        return $document;
    }
}

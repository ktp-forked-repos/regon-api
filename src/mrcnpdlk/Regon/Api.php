<?php

namespace mrcnpdlk\Regon;

use mrcnpdlk\Regon\Enum\Report;
use mrcnpdlk\Regon\Exception\InvalidResponse;
use mrcnpdlk\Regon\Model\Entity;
use mrcnpdlk\Regon\Model\Entity\Address;
use mrcnpdlk\Regon\Model\Entity\Owner;
use mrcnpdlk\Regon\Model\Entity\Register;
use mrcnpdlk\Regon\Model\SearchResult;

/**
 * Class Api
 *
 * @package mrcnpdlk\Regon
 */
class Api
{
    /**
     * @var Client
     */
    private $oNativeApi;

    /**
     * Api constructor.
     *
     * @param Client $oClient
     */
    public function __construct(Client $oClient)
    {
        $this->oNativeApi = NativeApi::create($oClient);
    }

    /**
     * @param string $nip
     *
     * @return SearchResult
     */
    public function getByNip(string $nip)
    {
        $tList = $this->oNativeApi->DaneSzukaj(null, $nip);

        return new SearchResult($tList[0]);
    }

    /**
     * @param string $krs
     *
     * @return SearchResult
     */
    public function getByKrs(string $krs)
    {
        $tList = $this->oNativeApi->DaneSzukaj(null, null, $krs);

        return new SearchResult($tList[0]);
    }

    /**
     * Getting current date of GUS database
     *
     * @return null|string Date in format YYYY-MM-DD
     */
    public function getServiceStatus()
    {
        $res = $this->oNativeApi->GetValue('StanDanych');

        if ($res) {
            return (new \DateTime($res))->format('Y-m-d');
        }

        return null;
    }

    public function getReport(string $regon)
    {
        $res           = null;
        $oSearchResult = $this->getByRegon($regon);
        switch ($oSearchResult->getTypeId()) {
            case 'P':
                break;
            case 'F':
                switch ($oSearchResult->getSilosId()) {
                    case '1':
                        $res = $this->getReportPhysicCeidg($regon);
                        break;
                    case '2':
                        $res = $this->oNativeApi->DanePobierzPelnyRaport($regon, Report::REPORT_ACTIVITY_PHYSIC_AGRO);
                        break;
                    default:
                        $res = $this->oNativeApi->DanePobierzPelnyRaport($regon, Report::REPORT_ACTIVITY_PHYSIC_OTHER);
                        break;
                }
                break;
            case 'LP':
                break;
            case 'LF':
                break;

        }

        return $res;
    }

    /**
     * @param string $regon
     *
     * @return SearchResult
     */
    public function getByRegon(string $regon)
    {
        $tList = $this->oNativeApi->DaneSzukaj($regon);

        return new SearchResult($tList[0]);
    }

    /**
     * @param string $regon
     *
     * @return \mrcnpdlk\Regon\Model\Entity
     */
    private function getReportPhysicCeidg(string $regon)
    {
        $oEntity = new Entity();

        $searchedItems = $this->oNativeApi->DanePobierzPelnyRaport($regon, Report::REPORT_ACTIVITY_PHYSIC_PERSON);
        $searchedItem  = $searchedItems[0];

        $oEntity->nip   = $searchedItem->fiz_nip;
        $oEntity->owner = new Owner($searchedItem->fiz_imie1, $searchedItem->fiz_nazwisko, $searchedItem->fiz_imie2);

        if ($searchedItem->fiz_dzialalnosciCeidg === '1') {
            $tReports = $this->oNativeApi->DanePobierzPelnyRaport($regon, Report::REPORT_ACTIVITY_PHYSIC_CEIDG);
            $res      = $tReports[0];
        } elseif ($searchedItem->fiz_dzialalnosciRolniczych === '1') {
            $tReports = $this->oNativeApi->DanePobierzPelnyRaport($regon, Report::REPORT_ACTIVITY_PHYSIC_AGRO);
            $res      = $tReports[0];
        } else {
            throw new InvalidResponse(sprintf(''));
        }


        $oEntity->regon     = $res->fiz_regon9;
        $oEntity->name      = $res->fiz_nazwa;
        $oEntity->nameShort = $res->fiz_nazwaSkrocona;

        $oDate          = new Entity\Date();
        $oDate->create  = $res->fiz_dataPowstania;
        $oDate->start   = $res->fiz_dataRozpoczeciaDzialalnosci;
        $oDate->add     = $res->fiz_dataWpisuDoREGONDzialalnosci;
        $oDate->suspend = $res->fiz_dataZawieszeniaDzialalnosci;
        $oDate->resume  = $res->fiz_dataWznowieniaDzialalnosci;
        $oDate->change  = $res->fiz_dataZaistnieniaZmianyDzialalnosci;
        $oDate->close   = $res->fiz_dataZakonczeniaDzialalnosci;
        $oDate->delete  = $res->fiz_dataSkresleniazRegonDzialalnosci;

        $oEntity->history = $oDate;

        $oEntity->contactPhone = $res->fiz_numerTelefonu;
        $oEntity->contactEmail = $res->fiz_adresEmail;
        if ($res->fizC_RodzajRejestru_Symbol === '151') { //CEIDG
            $oEntity->ceidg = $res->fizC_numerwRejestrzeEwidencji;
        }
        if ($res->fizC_numerwRejestrzeEwidencji) {
            $oEntity->register = new Register(
                $res->fizC_numerwRejestrzeEwidencji,
                $res->fizC_RodzajRejestru_Symbol,
                $res->fizC_RodzajRejestru_Nazwa,
                $res->fizC_dataWpisuDoRejestruEwidencji
            );
        }

        if ($res->fiz_adSiedzWojewodztwo_Symbol) {
            $oHeadAddress                 = new Address();
            $oHeadAddress->countryId      = $res->fiz_adSiedzKraj_Symbol;
            $oHeadAddress->countryName    = $res->fiz_adSiedzKraj_Nazwa;
            $oHeadAddress->provinceId     = $res->fiz_adSiedzWojewodztwo_Symbol;
            $oHeadAddress->provinceName   = $res->fiz_adSiedzWojewodztwo_Nazwa;
            $oHeadAddress->districtId     = $res->fiz_adSiedzPowiat_Symbol;
            $oHeadAddress->districtName   = $res->fiz_adSiedzPowiat_Nazwa;
            $oHeadAddress->communeId      = substr($res->fiz_adSiedzGmina_Symbol, 0, 2);
            $oHeadAddress->communeTypeId  = substr($res->fiz_adSiedzGmina_Symbol, 2, 1);
            $oHeadAddress->communeName    = $res->fiz_adSiedzGmina_Nazwa;
            $oHeadAddress->cityId         = $res->fiz_adSiedzMiejscowosc_Symbol;
            $oHeadAddress->cityName       = $res->fiz_adSiedzMiejscowosc_Nazwa;
            $oHeadAddress->postalCityId   = $res->fiz_adSiedzMiejscowoscPoczty_Symbol;
            $oHeadAddress->postalCityName = $res->fiz_adSiedzMiejscowoscPoczty_Nazwa;
            $oHeadAddress->postalCode     = $res->fiz_adSiedzKodPocztowy;
            $oHeadAddress->streetId       = $res->fiz_adSiedzUlica_Symbol;
            $oHeadAddress->streetName     = $res->fiz_adSiedzUlica_Nazwa;
            $oHeadAddress->homeNr         = $res->fiz_adSiedzNumerNieruchomosci;
            $oHeadAddress->flatNr         = $res->fiz_adSiedzNumerLokalu;
            $oEntity->addressHead         = $oHeadAddress;
        }

        return $oEntity;
    }

    public function getReportForPhysic(string $regon)
    {
        $searchedItems = $this->oNativeApi->DanePobierzPelnyRaport($regon, Report::REPORT_ACTIVITY_PHYSIC_PERSON);
        $searchedItem  = $searchedItems[0];
        if ($searchedItem->fiz_dzialalnosciCeidg === '1') {
            $tReports = $this->oNativeApi->DanePobierzPelnyRaport($regon, Report::REPORT_ACTIVITY_PHYSIC_CEIDG);
            $oData    = $tReports[0];
        } elseif ($searchedItem->fiz_dzialalnosciRolniczych === '1') {
            $tReports = $this->oNativeApi->DanePobierzPelnyRaport($regon, Report::REPORT_ACTIVITY_PHYSIC_AGRO);
            $oData    = $tReports[0];
        } elseif ($searchedItem->fiz_dzialalnosciPozostalych === '1') {
            $tReports = $this->oNativeApi->DanePobierzPelnyRaport($regon, Report::REPORT_ACTIVITY_PHYSIC_OTHER);
            $oData    = $tReports[0];
        } elseif ($searchedItem->fiz_dzialalnosciZKrupgn === '1') {
            $tReports = $this->oNativeApi->DanePobierzPelnyRaport($regon, Report::REPORT_ACTIVITY_PHYSIC_KRUPGN);
            $oData    = $tReports[0];
        } else {
            throw new InvalidResponse(sprintf(''));
        }

        $oEntity                     = new Entity($oData);
        $oEntity->basicLegalFormId   = $searchedItem->fiz_podstawowaFormaPrawna_Symbol;
        $oEntity->basicLegalFormName = $searchedItem->fiz_podstawowaFormaPrawna_Nazwa;
        $oEntity->nip                = $searchedItem->fiz_nip;
        $oEntity->regon              = $searchedItem->fiz_regon9;
        $oEntity->isActive           = empty($searchedItem->fiz_dataSkresleniazRegon);
        $oEntity->owner              = new Owner($searchedItem->fiz_imie1, $searchedItem->fiz_nazwisko, $searchedItem->fiz_imie2);

        return $oEntity;

    }

    public function getReportForLaw(string $regon)
    {
        $searchedItems = $this->oNativeApi->DanePobierzPelnyRaport($regon, Report::REPORT_PUBLIC_LAW);
        $oData         = $searchedItems[0];
        print_r($oData);
        $oEntity = new Entity($oData);

        return $oEntity;
    }

}

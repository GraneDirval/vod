<?php

namespace IdentificationBundle\Identification\Profiler;


use IdentificationBundle\BillingFramework\ID;
use IdentificationBundle\Identification\Service\Session\IdentificationFlowDataExtractor;
use IdentificationBundle\Repository\CarrierRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class FakeCarrierIdentifier extends DataCollector
{
    /**
     * @var CarrierRepositoryInterface
     */
    private $carrierRepository;

    /**
     * CustomDataCollector constructor.
     * @param CarrierRepositoryInterface $carrierRepository
     */
    public function __construct(CarrierRepositoryInterface $carrierRepository)
    {
        $this->carrierRepository = $carrierRepository;
    }


    /**
     * Collects data for the given Request and Response.
     *
     * @param Request    $request A Request instance
     * @param Response   $response A Response instance
     * @param \Exception $exception An Exception instance
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $session                        = $request->getSession();
        $this->data['current_identity'] = [
            'isp'            => IdentificationFlowDataExtractor::extractIspDetectionData($session),
            'identification' => IdentificationFlowDataExtractor::extractIdentificationData($session),
            'wifi_flow'      => $session->get('is_wifi_flow')
        ];
        $this->data['operators']        = $this->getFilteredOperators($this->getOperatorsList());

        return;
    }

    private function getFilteredOperators(array $operators): array
    {
        $indexedOperators = [];

        foreach ($operators as $operator) {
            if (!isset($operator['carrier'])) {
                continue;
            }
            $indexedOperators[$operator['carrier']] = $operator;
        }

        $carriers = $this->carrierRepository->findEnabledCarriers();

        $filteredOperators = [];
        foreach ($carriers as $carrier) {


            $key = $carrier->getBillingCarrierId();
            if (!isset($indexedOperators[$key])) {
                continue;
            }

            $filteredOperators[] = $indexedOperators[$key];
        }

        usort($filteredOperators, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $filteredOperators;
    }

    /**
     * https://docs.google.com/spreadsheets/d/1NTOylwWPOsAWDZDa1naXMNmzZEKODmHXFzGWuaLMBKE/edit?ts=5af987df&pli=1#gid=419530004
     * @return array
     */
    private function getOperatorsList(): array
    {
        return [
            ['name' => 'Jazz Pakistan', 'ip' => '119.160.116.250', 'carrier' => ID::MOBILINK_PAKISTAN, 'msisdn' => '923087654234'],
            ['name' => 'Zong Pakistan', 'ip' => '103.255.4.26', 'carrier' => ID::ZONG_PAKISTAN, 'msisdn' => '923165338483'],
            ['name' => 'Telenor Pakistan', 'ip' => '202.69.8.100', 'carrier' => ID::TELENOR_PAKISTAN, 'msisdn' => '772e9fde-68c6-3a31-8c29-dad92a0b11f8'],
            ['name' => 'Telenor Pakistan via DOT', 'ip' => '202.69.8.100', 'carrier' => ID::TELENOR_PAKISTAN_DOT, 'msisdn' => '923451440449'],
            ["name" => "Telenor Myanmar", 'ip' => '103.255.172.6', 'carrier' => ID::TELENOR_MYANMAR, 'msisdn' => '959762153238'],
            ['name' => 'Etisalat EGYPT', 'ip' => '197.124.76.20', 'carrier' => ID::ETISALAT_EGYPT, 'msisdn' => '201125936047'],
            ['name' => 'Orange EGYPT', 'ip' => '105.195.87.86', 'carrier' => ID::ORANGE_EGYPT, 'msisdn' => 'YAbaLlO8FxojykMU5byCoZx1DFbxYxGDF46O4btqlj0='],
            ['name' => 'Orange Tunisia', 'ip' => '41.231.31.52', 'carrier' => ID::ORANGE_TUNISIA, 'msisdn' => 'PDKSUB-200-8Cgd7CQU53KXe0vxO/9hL4QKNRE3zBjd/2pQ5pXrIzg='],
            ["name" => "Vodafone Egypt", "ip" => "196.151.0.179", 'carrier' => ID::VODAFONE_EGYPT, 'msisdn' => '201030214098'],
            ['name' => 'OOREDOO Algeria', 'ip' => '80.88.15.180', 'carrier' => ID::OOREDOO_ALGERIA, 'msisdn' => '213561939475'],
            ['name' => 'OOREDOO Kuwait', 'ip' => '217.69.182.68', 'carrier' => ID::OOREDOO_KUWAIT, 'msisdn' => '96560364659'],
            ['name' => 'OOREDOO Tunisia', 'ip' => '41.228.18.243', 'carrier' => ID::ZONG_PAKISTAN, 'msisdn' => '21623887936'],
            ['name' => 'OOREDOO Qatar', 'ip' => '212.77.211.241', 'carrier' => ID::OOREDOO_QATAR, 'msisdn' => '97433132342'],
            ['name' => 'OOREDOO Oman', 'ip' => '188.135.101.0', 'carrier' => ID::OOREDOO_OMAN, 'msisdn' => '96895765558'],
            ['name' => 'Indosat Indonesia', 'ip' => '202.93.36.12', 'msisdn' => '6285770021134'],
            ['name' => 'Smartfren Indonesia', 'ip' => '203.128.251.163', 'carrier' => ID::SMARTFEN_INDONESIA, 'msisdn' => ''],
            ["name" => "D-TaC Thailand", 'ip' => '1.46.165.24', 'msisdn' => '66617388995'],
            ["name" => "MTN Sudan", 'ip' => '41.223.163.37', 'carrier' => ID::MTN_SUDAN, 'msisdn' => '249925287738'],
            ["name" => "MTN South Africa", 'ip' => '105.237.239.18', 'msisdn' => ''],
            ["name" => "Zain Sudan", 'ip' => '41.223.202.37', 'msisdn' => '249910661412'],
            ["name" => "Zain Iraq", "ip" => "109.224.42.227", 'carrier' => ID::ZAIN_IRAQ, 'msisdn' => '9647823203941'],
            ["name" => "Zain Kuwait", "ip" => "37.39.111.84", 'carrier' => ID::ZAIN_KUWAIT, 'msisdn' => '96599586834'],
            ["name" => "Dialog Sri-Lanka", 'ip' => '175.157.192.31', 'carrier' => ID::DIALOG_SRILANKA, 'msisdn' => 'dmwdovWp46PHrfw='],
            ["name" => "K-Cell Kazakhstan", 'ip' => '2.72.213.204', 'carrier' => ID::KCELL_KAZAKHSTAN, 'msisdn' => '77757648591'],
            ["name" => "Beeline Russia", 'ip' => '95.31.224.44', 'msisdn' => ''],
            ["name" => "Airtel India", 'ip' => '182.66.11.31', 'carrier' => ID::AIRTEL_INDIA, 'msisdn' => '7347551848'],
            ["name" => "Globe Philippines", 'ip' => '112.198.81.245', 'carrier' => ID::GLOBE_PHILIPPINES, 'msisdn' => '639955851280'],
            ["name" => "Telcom Kenya", "ip" => "154.123.22.37", 'carrier' => ID::TELKOM_KENYA, 'msisdn' => '254774355676'],
            ["name" => "Jawwal Palestine", "ip" => "95.68.209.184", 'carrier' => ID::JAWWAL_PALESTINE, 'msisdn' => ''],
            ["name" => "Cellcard Kambodia", "ip" => "203.144.66.3", 'carrier' => ID::CELLCARD_CAMBODIA, 'msisdn' => '2784808e-8d18-37f6-88ba-fffccc0df307'],
            ["name" => "Tim Brazil", "ip" => "177.164.3.5", 'carrier' => ID::BRAZIL_TIM, 'msisdn' => '5511948514985'],
            ["name" => "Tigo Honduras", "ip" => "186.2.144.0", 'carrier' => ID::TIGO_HONDURAS, 'msisdn' => '50498170614'],
            ["name" => "Claro Nicaragua", "ip" => "200.62.96.0", 'carrier' => ID::CLARO_NICARAGUA, 'msisdn' => '50587239464'],
            ["name" => "Robi Bangladesh", "ip" => "202.134.14.136", 'carrier' => ID::ROBI_BANGLADESH, 'msisdn' => '8801813977240'],
            ["name" => "Hutch Indonesia", "ip" => "202.67.46.31", 'carrier' => ID::HUTCH3_INDONESIA_DOT, 'msisdn' => '62895361638546'],
            ["name" => "Orange Tunisia via MondiaMedia", "ip" => "196.233.233.36", 'carrier' => ID::ORANGE_TUNISIA_MM, 'msisdn' => ''],
            ["name" => "Vodafone EG via Tpay", "ip" => "196.159.145.123", 'carrier' => ID::VODAFONE_EGYPT_TPAY, 'msisdn' => '201057894589'],
            ["name" => "Orange EG via Tpay", "ip" => "45.96.44.38", 'carrier' => ID::ORANGE_EGYPT_TPAY, 'msisdn' => '201211745897'],
            ["name" => "Zain Saudi Arabia", "ip" => "51.39.204.42", 'carrier' => ID::ZAIN_SAUDI_ARABIA, 'msisdn' => '966580054879']
        ];
    }

    public function getCurrentIdentity()
    {
        return $this->data['current_identity'];
    }

    public function getOperators()
    {
        return $this->data['operators'];
    }

    /**
     * Returns the name of the collector.
     * @return string The collector name
     */
    public function getName()
    {
        return 'identification.fake_carrier_identifier';
    }

    public function reset()
    {
        // TODO: Implement reset() method.
    }
}
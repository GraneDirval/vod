<?php

namespace SubscriptionBundle\Admin\Controller;

use App\Domain\Entity\Affiliate;
use App\Domain\Entity\Campaign;
use App\Domain\Entity\Country;
use IdentificationBundle\Entity\User;
use Sonata\AdminBundle\Controller\CRUDController;
use SubscriptionBundle\Admin\Form\ComplaintsForm;
use SubscriptionBundle\Entity\Affiliate\AffiliateLog;
use SubscriptionBundle\Entity\Affiliate\CampaignInterface;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Service\ReportingToolService;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class ComplaintsAdminController
 */
class ComplaintsAdminController extends CRUDController
{
    /**
     * @var FormFactory
     */
    private $formFactory;

    /**
     * @var ReportingToolService
     */
    private $reportingToolService;

    /**
     * @var array
     */
    private $tableHeaders = [
        'Msisdn',
        'IP',
        'Device information',
        'Affiliate ID',
        'Affiliate Name',
        'Campaign ID',
        'Campaign Name',
        'Click ID',
        'The user came from',
        'Subscription date',
        'Unsubscription date',
        'Downloads number',
        'Games info',
        'Subscription attempts',
        'Resubscription attempts',
        'Amount of succes charges',
        'Total Amount Charged',
    ];

    public function __construct(FormFactory $formFactory, ReportingToolService $reportingToolService)
    {
        $this->formFactory = $formFactory;
        $this->reportingToolService = $reportingToolService;
    }

    public function createAction(Request $request = null)
    {
        $form = $this->formFactory->create(ComplaintsForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            /** @var UploadedFile $file */
            $file = $formData['file'];

            $msisdns = empty($file)
                ? [$formData['identifier']]
                : str_getcsv(file_get_contents($file->getRealPath()), ',');

            $usersData = $this->getUsersReport($msisdns);

            $content = $this->renderView('@SubscriptionAdmin/Complaints/report.html.twig', [
                'nonexistentUsers' => $usersData['nonexistentUsers'],
                'tableHeaders' => $this->tableHeaders,
                'users' => $usersData['users'],
                'admin' => $this->admin
            ]);
        } else {
            $content = $this->renderView('@SubscriptionAdmin/Complaints/complaints_form.html.twig', [
                'form' => $form->createView()
            ]);
        }

        return $this->renderWithExtraParams('@SubscriptionAdmin/Complaints/create.html.twig', [
            'content' => $content
        ]);
    }

    public function downloadCsvAction(Request $request)
    {
        $report = $this->getUsersReport($request->request->get('msisnds'));
        $usersData = $report['users'];

        $response = new StreamedResponse();
        $response->setCallback(function () use ($usersData) {
            $fp = fopen('php://output', 'wb');

            fputcsv($fp, $this->tableHeaders);

            foreach ($usersData as $row) {
                $row = array_map(function ($value) {
                    return $value instanceof \DateTime ? $value->format('Y-m-d H:i:s') : $value;
                }, $row);

                fputcsv($fp, $row);
            }

            fclose($fp);
        });

        $fileName = "Complaints-" . date("Y-m-d") . ".csv";

        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename=' . $fileName);

        return $response;
    }

    /**
     * @param array $msisdns
     *
     * @return array
     */
    private function getUsersReport(array $msisdns): array
    {
        $doctrine = $this->getDoctrine();
        $userRepository = $doctrine->getRepository(User::class);

        $nonexistentUsers = [];
        $usersInfo = [];

        foreach ($msisdns as $msisdn) {
            /** @var User $user */
            $user = $userRepository->findOneBy(['identifier' => $msisdn]);

            if (!empty($user)) {
                $subscriptionRepository = $doctrine->getRepository(Subscription::class);
                $affiliateLogRepository = $doctrine->getRepository(AffiliateLog::class);

                $usersInfo[$msisdn] = $this->getEmptyData();
                $usersInfo[$msisdn]['user'] = $user;
                $usersInfo[$msisdn]['ip'] = $user->getIp();

                /** @var Subscription $subscription */
                $subscription = $subscriptionRepository->findOneBy(['user' => $user]);
                $usersInfo[$msisdn]['subscription_date'] = empty($subscription) ? null : $subscription->getCreated();
                $usersInfo[$msisdn]['unsubscription_date'] =
                    $subscription->getCurrentStage() === Subscription::ACTION_UNSUBSCRIBE
                        ? $subscription->getUpdated()
                        : null;

                /** @var AffiliateLog $affiliateLog */
                $affiliateLog = $affiliateLogRepository->findOneBy(['userMsisdn' => $msisdn]);

                if (!empty($affiliateLog)) {
                    $affiliateRepository = $doctrine->getRepository(Affiliate::class);
                    $campaignRepository = $doctrine->getRepository(Campaign::class);

                    $usersInfo[$msisdn]['device_info'] = $affiliateLog->getFullDeviceInfo();

                    $affiliateParams = $affiliateLog->getCampaignParams();
                    $usersInfo[$msisdn]['url'] = empty($affiliateParams['url']) ? null : $affiliateParams['url'];
                    $usersInfo[$msisdn]['aff_id'] = $affiliateParams['pk_campaign'];

                    /** @var Affiliate $affiliate */
                    $affiliate = $affiliateRepository->findOneBy(['id' => $affiliateParams['pk_campaign']]);
                    $usersInfo[$msisdn]['aff_name'] = $affiliate ? $affiliate->getName() : null;

                    unset($affiliateParams['cid']);
                    unset($affiliateParams['pk_campaign']);
                    unset($affiliateParams['pk_kwd']);
                    unset($affiliateParams['url']);
                    unset($affiliateParams['sub_price']);

                    $usersInfo[$msisdn]['campaignParams'] = json_encode($affiliateParams);

                    /** @var CampaignInterface $campaign */
                    $campaign = $campaignRepository->findOneBy(['campaignToken' => $affiliateLog->getCampaignToken()]);

                    if (!empty($campaign)) {
                        $usersInfo[$msisdn]['aff_id'] = $campaign->getAffiliate()->getUuid();
                        $usersInfo[$msisdn]['aff_name'] = $campaign->getAffiliate()->getName();
                        $usersInfo[$msisdn]['campaign_id'] = $campaign->getUuid();
//                        $usersInfo[$msisdn]['campaign_name'] = $campaign->getGame()->getTitle();
                    }
                }

                $reportingToolResponse = $this->reportingToolService->getUserStats($user);

                if (isset($reportingToolResponse['data']['subs_total'])) {
                    if (isset($reportingToolResponse['data']['subs_before_success'])) {
                        $usersInfo[$msisdn]['subscription_attempts'] = $reportingToolResponse['data']['subs_before_success'];
                        $usersInfo[$msisdn]['resubs_attempts'] = $reportingToolResponse['data']['subs_total'] - $usersInfo[$msisdn]['subscription_attempts'];
                    } else {
                        $usersInfo[$msisdn]['resubs_attempts'] = $reportingToolResponse['data']['subs_total'];
                    }
                }

                if (isset($reportingToolResponse['data']['charges_successful_no'])) {
                    $usersInfo[$msisdn]['charges_successful_no'] = $reportingToolResponse['data']['charges_successful_no'];
                }

                if (isset($reportingToolResponse['data']['charges_successful_value'])) {
                    $countryRepository = $doctrine->getRepository(Country::class);

                    $country = $countryRepository->findOneBy(['countryCode' => $user->getCountry()]);
                    $currency = $country->getCurrencyCode();
                    $usersInfo[$msisdn]['charges_successful_value'] = $reportingToolResponse['data']['charges_successful_value'] . ' ' . $currency;
                }
            } else {
                $nonexistentUsers[] = $msisdn;
            }
        }

        return [
            'nonexistentUsers' => $nonexistentUsers,
            'users' => $usersInfo
        ];
    }

    /**
     * @return array
     */
    private function getEmptyData(): array
    {
        return [
            'device_info'              => null,
            'aff_id'                   => null,
            'aff_name'                 => null,
            'campaign_id'              => null,
            'campaign_name'            => null,
            'campaignParams'           => null,
            'url'                      => null,
            'gameDownloadCount'        => 0,
            'gamesInfo'                => null,
            'subscription_attempts'    => null,
            'resubs_attempts'          => null,
            'charges_successful_no'    => null,
            'charges_successful_value' => null,
        ];
    }
}
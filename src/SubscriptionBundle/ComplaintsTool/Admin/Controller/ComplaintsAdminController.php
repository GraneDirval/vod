<?php

namespace SubscriptionBundle\ComplaintsTool\Admin\Controller;

use CommonDataBundle\Repository\CountryRepository;
use Doctrine\ORM\EntityManagerInterface;
use IdentificationBundle\Entity\User;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Sonata\AdminBundle\Controller\CRUDController;
use SubscriptionBundle\ComplaintsTool\Admin\Form\ComplaintsForm;
use SubscriptionBundle\Entity\Affiliate\AffiliateInterface;
use SubscriptionBundle\Entity\Affiliate\AffiliateLog;
use SubscriptionBundle\Entity\Affiliate\CampaignInterface;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\ReportingTool\ReportingToolDataProvider;
use SubscriptionBundle\Repository\Affiliate\AffiliateLogRepository;
use SubscriptionBundle\Repository\Affiliate\CampaignRepositoryInterface;
use SubscriptionBundle\Repository\SubscriptionRepository;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
     * @var \SubscriptionBundle\ReportingTool\ReportingToolDataProvider
     */
    private $reportingToolService;

    /**
     * @var array
     */
    private $tableHeaders = [
        'user'                     => 'Msisdn',
        'ip'                       => 'IP',
        'device_info'              => 'Device information',
        'aff_id'                   => 'Affiliate ID',
        'aff_name'                 => 'Affiliate Name',
        'campaign_id'              => 'Campaign ID',
        'campaignParams'           => 'Click ID',
        'url'                      => 'The user came from',
        'subscription_date'        => 'Subscription date',
        'unsubscription_date'      => 'Unsubscription date',
        'subscription_attempts'    => 'Subscription attempts',
        'resubs_attempts'          => 'Resubscription attempts',
        'charges_successful_no'    => 'Amount of succes charges',
        'charges_successful_value' => 'Total Amount Charged',
    ];
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var SubscriptionRepository
     */
    private $subscriptionRepository;
    /**
     * @var AffiliateLogRepository
     */
    private $affiliateLogRepository;
    /**
     * @var CampaignRepositoryInterface
     */
    private $campaignRepository;
    /**
     * @var CountryRepository
     */
    private $countryRepository;

    public function __construct(
        FormFactory $formFactory,
        ReportingToolDataProvider $reportingToolService,
        EntityManagerInterface $entityManager,
        SubscriptionRepository $subscriptionRepository,
        AffiliateLogRepository $affiliateLogRepository,
        CampaignRepositoryInterface $campaignRepository,
        CountryRepository $countryRepository
    )
    {
        $this->formFactory            = $formFactory;
        $this->reportingToolService   = $reportingToolService;
        $this->entityManager          = $entityManager;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->affiliateLogRepository = $affiliateLogRepository;
        $this->campaignRepository     = $campaignRepository;
        $this->countryRepository      = $countryRepository;
    }

    /**
     * @param Request|null $request
     *
     * @return Response
     */
    public function reportAction(Request $request = null)
    {
        $form = $this->formFactory->create(ComplaintsForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            /** @var UploadedFile $file */

            $file    = $formData['file'];
            $msisdns = empty($file)
                ? [$formData['identifier']]
                : str_getcsv(file_get_contents($file->getRealPath()), ',');

            $usersData = $this->getUsersReport($msisdns);
            $content   = $this->renderView('@SubscriptionAdmin/Complaints/report.html.twig', [
                'nonexistentUsers' => $usersData['nonexistentUsers'],
                'tableHeaders'     => $this->tableHeaders,
                'users'            => $usersData['users'],
                'admin'            => $this->admin,
                'formExcel'        => $this->getFileDownloadFormView('downloadExcel', $msisdns),
                'formCsv'          => $this->getFileDownloadFormView('downloadCsv', $msisdns)
            ]);
        } else {
            $content = $this->renderView('@SubscriptionAdmin/Complaints/complaints_form.html.twig', [
                'form' => $form->createView()
            ]);
        }

        return $this->renderWithExtraParams('@SubscriptionAdmin/Complaints/make_report.html.twig', [
            'content' => $content
        ]);
    }

    /**
     * @param Request $request
     *
     * @return StreamedResponse
     */
    public function downloadCsvAction(Request $request)
    {
        $msisnds   = explode(',', $request->request->get('form')['msisdns']);
        $report    = $this->getUsersReport($msisnds);
        $usersData = $report['users'];

        $response = new StreamedResponse();
        $response->setCallback(function () use ($usersData) {
            $fp = fopen('php://output', 'wb');

            fputcsv($fp, $this->tableHeaders);

            $tableHeaderKeys = array_keys($this->tableHeaders);

            foreach ($usersData as $row) {
                $formattedRow = array_map(function ($headerKey) use ($row) {
                    $value = empty($row[$headerKey]) ? null : $row[$headerKey];

                    return $value instanceof \DateTime ? $value->format('Y-m-d H:i:s') : $value;
                }, $tableHeaderKeys);

                fputcsv($fp, $formattedRow);
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
     * @param Request $request
     *
     * @return StreamedResponse
     *
     * @throws Exception
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function downloadExcelAction(Request $request)
    {
        $msisnds   = explode(',', $request->request->get('form')['msisdns']);
        $report    = $this->getUsersReport($msisnds);
        $usersData = $report['users'];

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Complaints');
        $sheet->getDefaultColumnDimension()->setWidth(17);

        $row             = 1;
        $headerColumnKey = 1;

        foreach ($this->tableHeaders as $header) {
            $sheet->setCellValueByColumnAndRow($headerColumnKey, $row, $header);
            $headerColumnKey++;
        }

        $row++;
        $tableHeaderKeys = array_keys($this->tableHeaders);

        foreach ($usersData as $userData) {
            $formattedRow = array_map(function ($headerKey) use ($userData) {
                $value = empty($userData[$headerKey]) ? null : $userData[$headerKey];

                return $value instanceof \DateTime ? $value->format('Y-m-d H:i:s') : $value;
            }, $tableHeaderKeys);

            foreach ($formattedRow as $key => $cell) {
                $sheet->setCellValueByColumnAndRow($key + 1, $row, $cell);
            }

            $row++;
        }

        $writer   = new Xlsx($spreadsheet);
        $fileName = "Complaints-" . date("Y-m-d") . ".xlsx";

        $response = new StreamedResponse();
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Cache-Control', 'maxage=1');
        $response->headers->set('Content-Disposition', 'attachment; filename=' . $fileName);
        $response->setCallback(function () use ($writer) {
            $writer->save('php://output');
        });

        return $response;
    }

    /**
     * @param array $msisdns
     *
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function getUsersReport(array $msisdns): array
    {
        $doctrine       = $this->getDoctrine();
        $userRepository = $doctrine->getRepository(User::class);

        $nonexistentUsers = [];
        $usersInfo        = [];

        foreach ($msisdns as $msisdn) {
            /** @var User $user */
            $user = $userRepository->findOneBy(['identifier' => $msisdn]);

            if (
                !empty($user) &&
                $subscription = $this->subscriptionRepository->findCurrentSubscriptionByOwner($user)
            ) {

                $usersInfo[$msisdn]         = $this->getEmptyData();
                $usersInfo[$msisdn]['user'] = $user;
                $usersInfo[$msisdn]['ip']   = $user->getIp();

                /** @var Subscription $subscription */

                if ($subscription->getCurrentStage() === Subscription::ACTION_SUBSCRIBE) {
                    $usersInfo[$msisdn]['subscription_date'] = $subscription->getUpdated();
                } else {
                    $usersInfo[$msisdn]['unsubscription_date'] = $subscription->getUpdated();
                }

                /** @var AffiliateLog $affiliateLog */
                $affiliateLog = $this->affiliateLogRepository->findOneBy(['userMsisdn' => $msisdn]);

                if (!empty($affiliateLog)) {

                    $usersInfo[$msisdn]['device_info'] = $affiliateLog->getFullDeviceInfo();

                    /** @var CampaignInterface $campaign */
                    $campaign = $this->campaignRepository->findOneByCampaignToken($affiliateLog->getCampaignToken());

                    if (!empty($campaign)) {
                        /** @var AffiliateInterface $affiliate */
                        $affiliate = $campaign->getAffiliate();

                        $usersInfo[$msisdn]['url']            = $affiliateLog->getUrl();
                        $usersInfo[$msisdn]['aff_id']         = $campaign->getAffiliate()->getUuid();
                        $usersInfo[$msisdn]['aff_name']       = $affiliate->getName();
                        $usersInfo[$msisdn]['campaign_id']    = $campaign->getUuid();
                        $usersInfo[$msisdn]['campaignParams'] = json_encode($affiliateLog->getCampaignParams());
                    }
                }

                $reportingToolResponse = $this->reportingToolService->getUserStats($user);

                if (isset($reportingToolResponse['data']['subs_total'])) {
                    if (isset($reportingToolResponse['data']['subs_before_success'])) {
                        $usersInfo[$msisdn]['subscription_attempts'] = $reportingToolResponse['data']['subs_before_success'];
                        $usersInfo[$msisdn]['resubs_attempts']       = $reportingToolResponse['data']['subs_total'] - $usersInfo[$msisdn]['subscription_attempts'];
                    } else {
                        $usersInfo[$msisdn]['resubs_attempts'] = $reportingToolResponse['data']['subs_total'];
                    }
                }

                if (isset($reportingToolResponse['data']['charges_successful_no'])) {
                    $usersInfo[$msisdn]['charges_successful_no'] = $reportingToolResponse['data']['charges_successful_no'];
                }

                if (isset($reportingToolResponse['data']['charges_successful_value'])) {

                    $country = $this->countryRepository->findOneBy([
                        'countryCode' => $user->getCountry()
                    ]);

                    $currency                                       = $country->getCurrencyCode();
                    $usersInfo[$msisdn]['charges_successful_value'] = $reportingToolResponse['data']['charges_successful_value'] . ' ' . $currency;
                }
            } else {
                $nonexistentUsers[] = $msisdn;
            }
        }

        return [
            'nonexistentUsers' => $nonexistentUsers,
            'users'            => $usersInfo
        ];
    }

    /**
     * @return array
     */
    private function getEmptyData(): array
    {
        return [
            'subscription_date'        => null,
            'unsubscription_date'      => null,
            'device_info'              => null,
            'aff_id'                   => null,
            'aff_name'                 => null,
            'campaign_id'              => null,
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

    /**
     * @param string $action
     * @param array  $msisdns
     *
     * @return FormView
     */
    private function getFileDownloadFormView(string $action, array $msisdns): FormView
    {
        return $this
            ->createFormBuilder()
            ->setAction($this->admin->generateUrl($action))
            ->add('msisdns', HiddenType::class, ['data' => implode(',', $msisdns)])
            ->getForm()
            ->createView();
    }
}
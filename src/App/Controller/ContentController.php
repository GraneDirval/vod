<?php
/**
 * Created by PhpStorm.
 * User: Iliya Kobus
 * Date: 1/17/2019
 * Time: 2:19 PM
 */

namespace App\Controller;

use App\Domain\Service\FaqProviderService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ContentController extends AbstractController
{
    /** @var FaqProviderService $faqProviderService */
    protected $faqProviderService;

    /**
     * ContentController constructor.
     *
     * @param $faqProviderService
     */
    public function __construct(FaqProviderService $faqProviderService)
    {
        $this->faqProviderService = $faqProviderService;
    }

    /**
     * @Route("/faq",name="faq")
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \App\Exception\WrongTranslationRecordType
     */
    public function faqAction()
    {
        return $this->render(
            '@App/Content/faq.html.twig',
            [
                'questions' => $this->faqProviderService->getSortedQuestions(),
                'answers'   => $this->faqProviderService->getSortedAnswers(),
            ]
        );
    }

    /**
     * @Route("/terms-and-conditions",name="terms_and_conditions")
     */
    public function termsAndConditionsAction()
    {
        return $this->render(
            '@App/Content/terms_and_conditions.html.twig'
        );
    }
}
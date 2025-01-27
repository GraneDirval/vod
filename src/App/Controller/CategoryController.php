<?php

namespace App\Controller;

use App\Domain\DTO\BatchOfNotExpiredVideos;
use App\Domain\Repository\MainCategoryRepository;
use App\Domain\Repository\SubcategoryRepository;
use App\Domain\Repository\UploadedVideoRepository;
use App\Piwik\ContentStatisticSender;
use App\Domain\Service\VideoProcessing\UploadedVideoSerializer;
use CommonDataBundle\Service\TemplateConfigurator\TemplateConfigurator;
use IdentificationBundle\Identification\DTO\ISPData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CategoryController
 */
class CategoryController extends AbstractController implements AppControllerInterface
{
    /** @var CategoryController */
    private $mainCategoryRepository;

    /** @var UploadedVideoRepository */
    private $uploadedVideoRepository;

    /** @var SubcategoryRepository */
    private $subcategoryRepository;

    /** @var ContentStatisticSender */
    private $contentStatisticSender;

    /** @var TemplateConfigurator */
    private $templateConfigurator;

    /** @var UploadedVideoSerializer */
    private $videoSerializer;

    /**
     * CategoryController constructor.
     *
     * @param MainCategoryRepository $mainCategoryRepository
     * @param UploadedVideoRepository $uploadedVideoRepository
     * @param SubcategoryRepository $subcategoryRepository
     * @param ContentStatisticSender $contentStatisticSender
     * @param TemplateConfigurator $templateConfigurator
     * @param UploadedVideoSerializer $videoSerializer
     */
    public function __construct(
        MainCategoryRepository $mainCategoryRepository,
        UploadedVideoRepository $uploadedVideoRepository,
        SubcategoryRepository $subcategoryRepository,
        ContentStatisticSender $contentStatisticSender,
        TemplateConfigurator $templateConfigurator,
        UploadedVideoSerializer $videoSerializer
    ) {
        $this->mainCategoryRepository  = $mainCategoryRepository;
        $this->uploadedVideoRepository = $uploadedVideoRepository;
        $this->subcategoryRepository   = $subcategoryRepository;
        $this->contentStatisticSender  = $contentStatisticSender;
        $this->templateConfigurator    = $templateConfigurator;
        $this->videoSerializer         = $videoSerializer;
    }


    /**
     * @Route("/category/{categoryUuid}",name="show_category")
     *
     * @param string $categoryUuid
     * @param Request $request
     * @param ISPData $data
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Exception
     */
    public function showCategoryAction(string $categoryUuid, Request $request, ISPData $data)
    {
        if (!$category = $this->mainCategoryRepository->findOneBy(['uuid' => $categoryUuid])) {
            throw new NotFoundHttpException('Category is not found');
        }

        $subcategories = $this->subcategoryRepository->findBy(['parent' => $category]);

        /** @var  BatchOfNotExpiredVideos $videos */
        if ($subcategoryUuid = $request->get('subcategoryUuid', '')) {
            $selectedSubcategory = $this->subcategoryRepository->findOneBy([
                'parent' => $category,
                'uuid'   => $subcategoryUuid
            ]);
            $videos = $this->uploadedVideoRepository->findNotExpiredBySubcategories([$selectedSubcategory]);
        } else {
            $videos = $this->uploadedVideoRepository->findNotExpiredBySubcategories($subcategories);
        }

        $categoryVideos = [];

        foreach ($videos->getVideos() as $video) {
            $categoryVideos[$video->getUuid()] = $this->videoSerializer->serializeShort($video);
        }

        $this->contentStatisticSender->trackVisit($request->getSession());

        $template = $this->templateConfigurator->getTemplate('category', $data->getCarrierId());

        return $this->render($template, [
            'videos'              => $categoryVideos,
            'defaultVideo'        => array_shift($categoryVideos),
            'isLast'              => $videos->isLast(),
            'category'            => $category,
            'subcategories'       => $subcategories,
            'selectedSubcategory' => $selectedSubcategory ?? null
        ]);
    }
}
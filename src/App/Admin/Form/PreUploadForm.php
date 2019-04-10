<?php

namespace App\Admin\Form;

use App\Domain\Entity\MainCategory;
use App\Domain\Entity\Subcategory;
use App\Domain\Entity\VideoPartner;
use App\Domain\Repository\SubcategoryRepository;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class UploadVideoForm
 */
class PreUploadForm extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('videoPartner', EntityType::class, [
                'class' => VideoPartner::class,
                'placeholder' => 'Select video partner'
            ])
            ->add('expiredDate', DateTimePickerType::class, [
                'required' => false,
                'format' => 'Y-MM-dd HH:mm',
                'attr' => ['autocomplete' => 'off']
            ])
            ->add('preset', ChoiceType::class, [
                'choices' => $options['presets'],
                'mapped' => false,
                'label' => 'Preset',
                'placeholder' => 'Select uploading preset'
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $uploadedVideo = $event->getData();
            $this->addCategoryFields($event->getForm(), $uploadedVideo ? $uploadedVideo['mainCategory'] : null);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $this->addCategoryFields($event->getForm(), $data['mainCategory']);
        });
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'presets' => [],
            'csrf_protection' => false,
        ]);
    }

    /**
     * @param FormInterface $form
     * @param string $mainCategoryId
     */
    private function addCategoryFields(FormInterface $form, string $mainCategoryId = null)
    {
        $form
            ->add('mainCategory', EntityType::class, [
                'class' => MainCategory::class,
                'mapped' => false,
                'placeholder' => 'Select main category'
            ])
            ->add('subcategory', EntityType::class, [
                'query_builder' => function (SubcategoryRepository $subcategoryRepository) use ($mainCategoryId) {
                    return $subcategoryRepository
                        ->createQueryBuilder('sc')
                        ->where('sc.parent = :mainId')
                        ->setParameter('mainId', $mainCategoryId);
                },
                'class' => Subcategory::class,
                'placeholder' => 'Select subcategory'
            ]);
    }
}
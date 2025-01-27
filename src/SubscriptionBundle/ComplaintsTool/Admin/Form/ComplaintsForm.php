<?php

namespace SubscriptionBundle\ComplaintsTool\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

/**
 * Class ComplaintsForm
 */
class ComplaintsForm extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('identifier', TextType::class, [
                'required' => false,
                'attr' => ['class' => 'col-md-4 mb-3 form-control'],
                'label' => 'Please enter the msisdn'
            ])
            ->add('file', FileType::class, [
                'required' => false,
                'attr' => ['class' => 'col-md-4 mb-3 form-control'],
                'constraints' => [
                    new File([
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain',
                            'text/tsv'
                        ],
                        'mimeTypesMessage' => 'Invalid file format. Available extensions .csv, .tsv, .txt (comma separated text files)'
                    ])
                ],
                'label' => 'Or choose the .csv file'
            ]);
    }
}
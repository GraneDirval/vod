<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 04.02.19
 * Time: 17:19
 */

namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class ContactUsType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class, [
                'required' => true,
                'constraints' => [new Email()]])
            ->add('comment', TextareaType::class, [
                'required' => true,
                'constraints' => [
                    new Length(['min' => 1]),
                    new NotBlank(),
                    new NotNull()
                ]
                ]);

        parent::buildForm($builder, $options); // TODO: Change the autogenerated stub
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id'   => 'contact-us'
        ]);

        parent::configureOptions($resolver); // TODO: Change the autogenerated stub
    }


}
<?php

namespace SubscriptionBundle\Admin;

use App\Domain\Entity\Carrier;
use App\Domain\Entity\Country;
use App\Utils\UuidGenerator;
use PriceBundle\Entity\Strategy;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ChoiceFieldMaskType;
use Sonata\AdminBundle\Route\RouteCollection;
use SubscriptionBundle\BillingFramework\Process\Exception\BillingFrameworkException;
use SubscriptionBundle\BillingFramework\Process\SubscriptionPackDataProvider;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use SubscriptionBundle\Entity\Price;
use SubscriptionBundle\Entity\SubscriptionPack;
use SubscriptionBundle\Service\SubscriptionTextService;

class SubscriptionPackAdmin extends AbstractAdmin
{
    /**
     * @var SubscriptionTextService
     */
    private $subscriptionTextService;

    /**
     * @var SubscriptionPackDataProvider
     */
    private $subscriptionPackDataProvider;

    /**
     * @param string $code
     * @param string $class
     * @param string $baseControllerName
     * @param SubscriptionPackDataProvider $subscriptionPackDataProvider
     * @param SubscriptionTextService $subscriptionTextService
     */
    public function __construct(
        $code,
        $class,
        $baseControllerName,
        SubscriptionPackDataProvider $subscriptionPackDataProvider,
        SubscriptionTextService $subscriptionTextService
    ) {
        parent::__construct($code, $class, $baseControllerName);

        $this->subscriptionTextService = $subscriptionTextService;
        $this->subscriptionPackDataProvider = $subscriptionPackDataProvider;
    }

    /**
     * @return SubscriptionPack
     *
     * @throws \Exception
     */
    public function getNewInstance()
    {
        $nowDate = new \DateTime('now');

        /** @var SubscriptionPack $instance */
        $instance = new SubscriptionPack(UuidGenerator::generate());
        $instance->setCustomRenewPeriod(0);
        $instance->setUnlimited(false);
        $instance->setCredits(0);
        $instance->setCreated($nowDate);
        $instance->setUpdated($nowDate);

        return $instance;
    }

    /**
     * @param SubscriptionPack $object
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function preUpdate($object)
    {
        $this->subscriptionTextService->insertDefaultPlaceholderTexts($object);
        parent::preUpdate($object);
    }

    /**
     * @param string $action
     * @param null $object
     *
     * @return array
     */
    public function getActionButtons($action, $object = null)
    {
        return array_merge(
            parent::getActionButtons($action, $object),
            ['template' => '@SubscriptionBundle/SubscriptionPack/go-to-texts-button.html.twig']
        );
    }

    /**
     * @param RouteCollection $collection
     */
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('success', $this->getRouterIdParameter() . '/success');
        $collection->add('texts', $this->getRouterIdParameter() . '/texts');

        parent::configureRoutes($collection); // TODO: Change the autogenerated stub
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('name')
            ->add('renewStrategy', null, [
                'editable' => false
            ])
            ->add('buyStrategy', null, [
                'editable' => false
            ])
            ->add('carrier')
            ->add('unlimited', null, [
                'editable' => false,
                'label'    => 'Unlimited Downloads'
            ])
            ->add('credits', null, [
                'editable' => false,
                'label'    => 'Credits'
            ])
            ->add('periodicity', ChoiceType::class, [
                'editable' => true,
                'choices'  => array_flip(SubscriptionPack::PERIODICITY),
            ])
            ->add('status', ChoiceType::class, [
                'editable' => true,
                'choices'  => array_flip(SubscriptionPack::STATUSES),
            ]);
    }

    /**
     * @param DatagridMapper $datagridMapper
     *
     * @throws BillingFrameworkException
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
         $carriers = $this->subscriptionPackDataProvider->getCarriers();
         $tiers = $this->subscriptionPackDataProvider->getTiers();
         $strategies = $this->subscriptionPackDataProvider->getBillingStrategies();

         $datagridMapper
             ->add('name')
             ->add('country')
             ->add('carrier', null, [], ChoiceType::class, [
                 'choices' => $carriers,
                 'choice_label' => 'name',
                 'choice_value' => 'id'
             ])
             ->add('buyStrategy', null, [], ChoiceType::class, [
                 'choices'      => $strategies,
                 'choice_label' => 'name',
                 'choice_value' => 'id'
             ])
             ->add('renewStrategy', null, [], ChoiceType::class, [
                 'choices'      => $strategies,
                 'choice_label' => 'name',
                 'choice_value' => 'id'
             ])
             ->add('periodicity')
             ->add('tier', null, [], ChoiceType::class, [
                 'choices'      => $tiers,
                 'choice_label' => 'name',
                 'choice_value' => 'id'
             ])
             ->add('status', null, [
                 'label' => 'Subscription Pack Active'
             ])
//             ->add('preferredRenewalStart', 'doctrine_orm_datetime_range', [
//                 'field_type' => 'sonata_type_datetime_range_picker'
//             ])
//             ->add('preferredRenewalEnd', 'doctrine_orm_datetime_range', [
//                 'field_type' => 'sonata_type_datetime_range_picker'
//             ])
         ;
    }

    /**
     * @param FormMapper $formMapper
     *
     * @throws BillingFrameworkException
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $this->buildGeneralSection($formMapper);
        $this->buildBillingStrategySection($formMapper);
        $this->buildPromotionSections($formMapper);
    }

    /**
     * @param FormMapper $formMapper
     */
    private function buildGeneralSection(FormMapper $formMapper)
    {
        $formMapper->add('name', TextType::class);
        $formMapper->add('description', TextareaType::class, ['required' => false]);
        $formMapper->add('country', EntityType::class,
            ['class'    => Country::class, 'expanded' => false,
                'required' => true, 'placeholder' => 'Please select country']);

        $builder = $formMapper->getFormBuilder();

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var SubscriptionPack $subscriptionPack */
            $subscriptionPack = $event->getData();

            if ($subscriptionPack) {
                $this->appendCarrierField($event->getForm(), $subscriptionPack->getCountry());
                $this->appendTierField($event->getForm(), $subscriptionPack->getCarrierId());
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($builder) {
            $formValues = $this->getRequest()->request->get($builder->getFormConfig()->getName());
            $carrier  = isset($formValues['carrierId']) ? $formValues['carrierId'] : null;
            $this->appendTierField($event->getForm(), $carrier);
        }, 899);

        $builder->get('country')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $country = $event->getForm()->getData();
            $this->appendCarrierField($event->getForm()->getParent(), $country);
            $event->stopPropagation();
        }, 901);

        $formMapper
            ->add('displayCurrency', TextType::class, [
                'required' => false,
                'label'    => 'Display currency symbol'
            ])
            ->add('tierPrice', HiddenType::class, [
                'required' => true
            ])
            ->add('tierCurrency', HiddenType::class, [
                'required' => true
            ]);

        $formMapper
            ->add('periodicity', ChoiceFieldMaskType::class, [
                'choices'     => SubscriptionPack::PERIODICITY,
                'map'         => [
                    SubscriptionPack::CUSTOM_PERIODICITY => ['customRenewPeriod']
                ],
                'placeholder' => 'Please select periodicity',
                'required'    => true,
                'label'       => 'Periodicity'
            ])
            ->add('customRenewPeriod', IntegerType::class, [
                'required' => true,
                'label' => 'No of subscribed days before auto renewal'
            ]);

        $formMapper
            ->add('unlimited', ChoiceFieldMaskType::class, [
                'choices'     => [
                    'Specify Credits' => 0,
                    'Unlimited'       => 1,
                ],
                'map'         => [
                    0 => ['credits'],
                ],
                'placeholder' => 'Please select credits',
                'required'    => true,
                'label'       => 'No of games to be downloaded'
            ])
            ->add('credits', IntegerType::class, [
                'required' => true,
                'label'    => 'No Of Games Can Be Downloaded in subscription period'
            ]);

        $formMapper
            ->add('unlimitedGracePeriod', ChoiceFieldMaskType::class, array(
                'choices'     => [
                    'Specify Days' => 0,
                    'Infinite'     => 1,
                ],
                'map'         => [
                    0 => ['gracePeriod'],
                ],
                'placeholder' => 'Please select gace period',
                'required'    => true,
                'label'       => 'Credit expiration time',
                'help'        => 'A number of days that the user can download his credits after he is un-subscribed'
            ))
            ->add('gracePeriod', IntegerType::class);

        $formMapper
            ->add('preferredRenewalStart', TimeType::class, [
                'input'    => 'datetime',
                'required' => false,
                'widget'   => 'choice',
                'label'    => 'Preferred Renewal Start Time'
            ])
            ->add('preferredRenewalEnd', TimeType::class, [
                'input'    => 'datetime',
                'required' => false,
                'widget'   => 'choice',
                'label'    => 'Preferred Renewal End Time'
            ]);

        $formMapper
            ->add('welcomeSMSText', TextareaType::class, [
                'required' => false,
                'label'    => 'Welcome SMS Text'
            ])
            ->add('unsubscribeSMSText', TextareaType::class, [
                'required' => false,
                'label'    => 'Unsubscribe SMS Text'
            ])
            ->add('renewalSMSText', TextareaType::class, [
                'required' => false,
                'label'    => 'Renewal SMS Text'
            ]);

        $formMapper
            ->add('status', ChoiceType::class, [
                'choices' => SubscriptionPack::STATUSES,
                'label'   => 'Pack Status'
            ])
            ->end();
    }

    /**
     * @param FormMapper $formMapper
     *
     * @throws BillingFrameworkException
     */
    private function buildBillingStrategySection(FormMapper $formMapper)
    {
        $billingStrategies = $this->subscriptionPackDataProvider->getBillingStrategies();

        $generalOptions = [
            'choices' => $billingStrategies,
            'choice_label' => 'name',
            'choice_attr' => function ($strategy) {
                return ['data' => $strategy->id];
            },
            'choice_value' => function ($strategy) {
                return $strategy instanceof Strategy ? $strategy->getName() : null;
            }
        ];

        $buyStrategyOptions = array_merge(['label' => 'Billing strategy for new subscription'], $generalOptions);
        $renewStrategyOptions = array_merge(['label' => 'Billing strategy for new subscription'], $generalOptions);

        $formMapper
            ->with('Billing strategy', [''])
            ->add('buyStrategy', ChoiceType::class, $buyStrategyOptions)
            ->add('buyStrategyId', HiddenType::class, ['required' => false]);

        $formMapper
            ->add('renewStrategy', ChoiceType::class, $renewStrategyOptions)
            ->add('renewStrategyId', HiddenType::class, ['required' => false]);

        $formMapper
            ->add('providerManagedSubscriptions', CheckboxType::class, [
                'required' => false,
                'label'    => 'Subscriptions will be managed by provider'
            ])
            ->add('isResubAllowed', CheckboxType::class, [
                'required' => false,
                'label'    => 'Is resubscribe allowed'
            ])
            ->end();
    }

    /**
     * @param FormMapper $formMapper
     */
    private function buildPromotionSections(FormMapper $formMapper)
    {
        $formMapper
            ->with('Promotion 1', [''])
            ->add('firstSubscriptionPeriodIsFree', ChoiceFieldMaskType::class, [
                'choices'  => [
                    'Yes' => 1,
                    'No'  => 0
                ],
                 'map'      => [
                     1 => ['firstSubscriptionPeriodIsFreeMultiple']
                 ],
                'required' => true,
                'label'    => '1st subscription period is free (user will not be charged upon subscription)'
            ])
            ->add('firstSubscriptionPeriodIsFreeMultiple', CheckboxType::class, [
                'label'    => 'The user benefit from the same offer type more than once',
                'required' => false
            ])
            ->end();

        $formMapper
            ->with('Promotion 2')
            ->add('allowBonusCredit', ChoiceFieldMaskType::class, [
                'choices'  => [
                    'Yes' => 1,
                    'No'  => 0
                ],
                 'map'      => [
                     1 => ['bonusCredit', 'allowBonusCreditMultiple']
                 ],
                'required' => true,
                'label'    => 'Add bonus credit for first subscription period'
            ])
            ->add('bonusCredit', IntegerType::class, [
                'label'    => 'Please specify bonus credit',
                'required' => false
            ])
            ->add('allowBonusCreditMultiple', CheckboxType::class, [
                'label'    => 'The user benefit from the same offer type more than once',
                'required' => false
            ])
            ->end();
    }

    /**
     * @param FormInterface $form
     * @param Country|null $country
     *
     * @throws BillingFrameworkException
     */
    private function appendCarrierField(FormInterface $form, Country $country = null)
    {
        if ($country === null) {
            return;
        }

        /** @var Carrier[] $carriers */
        $carriers = $this->subscriptionPackDataProvider->getCarriersForCountry($country);

        if (count($carriers) > 0) {
            $form
                ->add('carrier', ChoiceType::class, [
                    'choices' => $carriers,
                    'choice_label' => 'name',
                    'choice_attr' => function ($carrier) {
                        return ['data' => $carrier->id];
                    },
                    'placeholder' => 'Please select carrier',
                    'required' => true
                ])
                ->add('carrierId', HiddenType::class, ['required' => false]);
        }
    }

    /**
     * @param FormInterface $form
     * @param integer $carrierId
     *
     * @throws BillingFrameworkException
     */
    private function appendTierField(FormInterface $form, $carrierId = null)
    {
        if ($carrierId === null) {
            return;
        }

        /** @var Price[] $carriers */
        $prices = $this->subscriptionPackDataProvider->getTiersForCarrier($carrierId);

        if (count($prices) > 0) {
            $form
                ->add('tier', ChoiceType::class, [
                    'choices' => $prices,
                    'choice_label' => 'name',
                    'choice_value' => function ($price) {
                        return $price instanceof Price ? "{$price->getName()} ({$price->getBfTierId()})" : null;
                    },
                    'choice_attr' => function (Price $price) {
                        return [
                            'data' => $price->getBfTierId(),
                            'data-price' => $price->getPriceWithTax() > 0
                                ? $price->getPriceWithTax()
                                : $price->getValue(),
                            'data-currency' => $price->getCurrency(),
                        ];
                    },
                    'placeholder'  => 'Please select tier',
                ])
                ->add('tierId', HiddenType::class, ['required' => false]);
        } else {
            $form->remove('tier');
        }
    }
}
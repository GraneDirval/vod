<?php
/**
 * Created by IntelliJ IDEA.
 * User: bharatm
 * Date: 07/08/17
 * Time: 10:37 AM
 */

namespace SubscriptionBundle\Admin;


use App\Domain\Entity\Carrier;
use App\Domain\Entity\Country;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use SubscriptionBundle\BillingFramework\Process\SubscriptionPackDataProvider;
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
     * @var \SubscriptionBundle\BillingFramework\Process\SubscriptionPackDataProvider
     */
    private $subscriptionPackDataProvider;


    /**
     * @param string                  $code
     * @param string                  $class
     * @param string                  $baseControllerName
     * @param SubscriptionTextService $subscriptionTextService
     */
    public function __construct(
        $code,
        $class,
        $baseControllerName,
        SubscriptionPackDataProvider $subscriptionPackDataProvider,
        SubscriptionTextService $subscriptionTextService
    )
    {
        parent::__construct($code, $class, $baseControllerName);

        $this->subscriptionTextService      = $subscriptionTextService;
        $this->subscriptionPackDataProvider = $subscriptionPackDataProvider;
    }

    /**
     * @param $object
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function preUpdate($object)
    {
        $this->subscriptionTextService->insertDefaultPlaceholderTexts($object);
        parent::preUpdate($object);
    }

    public function getActionButtons($action, $object = null)
    {

        return array_merge(
            parent::getActionButtons($action, $object),
            ['template' => '@SubscriptionBundleV2/SubscriptionPack/go-to-texts-button.html.twig']
        );
    }


    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('success', $this->getRouterIdParameter() . '/success');
        $collection->add('texts', $this->getRouterIdParameter() . '/texts');

        parent::configureRoutes($collection); // TODO: Change the autogenerated stub
    }


    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper->add('name', 'text');
        $formMapper->add('description', 'textarea', ['required' => false]);
        $formMapper->add('country', 'entity',
            ['class'    => Country::class, 'expanded' => false,
             'required' => true, 'placeholder' => 'Please select country']);

        $builder = $formMapper->getFormBuilder();

        $formModifierCarrier = function (FormInterface $form, $carrierId) {
            /** @var Price[] $carriers */
            $prices = null === $carrierId ? [] :
                $this->subscriptionPackDataProvider->getTiersForCarrier($carrierId);

            if (is_array($prices) && count($prices) > 0) {
                $attr = function ($val) {
                    return ['data' => $val->getTierId()];
                };

                $value = function ($val) {
                    if ($val instanceof Price) {
                        return $val->getName(). " ({$val->getTierId()})";
                    } else {
                        return $val;
                    }
                };

                $this->addBillingFrameworkField($form, "tier", [
                    'placeholder'  => 'Please select tier',
                    'choices'      => $prices,
                    'choice_value' => $value,
                    'choice_attr'  => $attr]);
            } else {
                $form->remove('tier');
            }
        };
        $formModifier        = function (FormInterface $form, Country $country = null) {
            /** @var Carrier[] $carriers */
            $carriers = null === $country ? [] :
                $this->subscriptionPackDataProvider->getCarriersForCountry($country);


            if (is_array($carriers) && count($carriers) > 0) {
                $this->addBillingFrameworkField($form, "carrier", ['choices'     => $carriers,
                                                                   'placeholder' => 'Please select carrier'
                ]);
            }


        };
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formModifier, $formModifierCarrier) {
                /** @var SubscriptionPack $subscriptionPack */
                $subscriptionPack = $event->getData();
                if ($subscriptionPack) {
                    $formModifier($event->getForm(), $subscriptionPack->getCountry());
                    $formModifierCarrier($event->getForm(), $subscriptionPack->getCarrierId());
                }

            }
        );

        $builder->addEventListener(FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($formModifierCarrier, $builder) {
                $formValues = $this->getRequest()->request->get($builder->getFormConfig()->getName());
                $carrierId  = isset($formValues['carrierId']) ? $formValues['carrierId'] : null;
                $formModifierCarrier($event->getForm(), $carrierId);
            }
            , 899);

        $builder->get('country')->addEventListener(FormEvents::POST_SUBMIT,

            function (FormEvent $event) use ($formModifier, $formModifierCarrier, $builder) {
                $country = $event->getForm()->getData();
                $formModifier($event->getForm()->getParent(), $country);
                $event->stopPropagation();
            }
            , 901);

        $formMapper->add('display_currency', 'text', [
            'required' => false,
            'label'    => 'Display currency symbol'
        ]);
        $formMapper->add("tier_price", HiddenType::class, ['required' => true]);
        $formMapper->add("tier_currency", HiddenType::class, ['required' => true]);

        $formMapper
            ->add('periodicity', 'sonata_type_choice_field_mask', array(
                'choices'     => SubscriptionPack::PERIODICITY,
                'map'         => array(
                    SubscriptionPack::CUSTOM_PERIODICITY => array('customRenewPeriod'),
                ),
                'placeholder' => 'Please select periodicity',
                'required'    => true,
                'label'       => 'Periodicity'
            ))
            ->add('customRenewPeriod', 'integer',
                [
                    'required' => true,
                    'label'    => 'No of subscribed days before auto renewal',

                ]
            );

        $formMapper
            ->add('unlimited', 'sonata_type_choice_field_mask', array(
                'choices'     => [
                    'Specify Credits' => 0,
                    'Unlimited'       => 1,
                ],
                'map'         => array(
                    0 => array('credits'),
                ),
                'placeholder' => 'Please select credits',
                'required'    => true,
                'label'       => 'No of games to be downloaded'
            ))
            ->add('credits', 'integer',
                [
                    'required' => true,
                    'label'    => 'No Of Games Can Be Downloaded in subscription period',

                ]
            );
        $formMapper
            ->add('unlimitedGracePeriod', 'sonata_type_choice_field_mask', array(
                'choices'     => [
                    'Specify Days' => 0,
                    'Infinite'     => 1,
                ],
                'map'         => array(
                    0 => array('gracePeriod'),
                ),
                'placeholder' => 'Please select gace period',
                'required'    => true,
                'label'       => 'Credit expiration time',
                'help'        => 'A number of days that the user can download his credits after he is un-subscribed'
            ))
            ->add('gracePeriod', 'integer');


        $formMapper->add('preferredRenewalStart', TimeType::class, [
            'input'    => 'datetime',
            'required' => false,
            'widget'   => 'choice',
            'label'    => 'Preferred Renewal Start Time'
        ]);
        $formMapper->add('preferredRenewalEnd', TimeType::class, [
            'input'    => 'datetime',
            'required' => false,
            'widget'   => 'choice',
            'label'    => 'Preferred Renewal End Time'
        ]);


        $formMapper->add('welcomeSMSText', 'textarea', ['required' => false,
                                                        'label'    => 'Welcome SMS Text'
        ]);
        $formMapper->add('unsubscribeSMSText', 'textarea', ['required' => false,
                                                            'label'    => 'Unsubscribe SMS Text'
        ]);
        $formMapper->add('renewalSMSText', 'textarea', ['required' => false,
                                                        'label'    => 'Renewal SMS Text'
        ]);

        $billingStrategies = $this->subscriptionPackDataProvider->getBillingStrategies();
        $this->addBillingFrameworkField(
            $formMapper,
            "buyStrategy",
            ["choices" => $billingStrategies, "label" => "Billing strategy for new subscription"]
        );

        $this->addBillingFrameworkField(
            $formMapper,
            "renewStrategy",
            ["choices" => $billingStrategies, "label" => "Billing strategy for renewals"]
        );

        $formMapper->add('providerManagedSubscriptions', 'checkbox', ['required' => false,
                                                                      'label'    => 'Subscriptions will be managed by provider'
        ]);

        $formMapper->add('isResubAllowed', 'checkbox', ['required' => false,
                                                        'label'    => 'is resubscribe allowed']);

        $formMapper->end()
            ->with('Promotion 1', [''])
            ->add('firstSubscriptionPeriodIsFree', 'sonata_type_choice_field_mask', [
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
            ->add('firstSubscriptionPeriodIsFreeMultiple', 'checkbox', [
                'label'    => 'The user benefit from the same offer type more than once',
                'required' => false
            ])
            ->end();

        $formMapper
            ->with('Promotion 2')
            ->add('allowBonusCredit', 'sonata_type_choice_field_mask', [
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
            ->add('bonusCredit', 'integer', [
                'label'    => 'Please specify bonus credit',
                'required' => false
            ])
            ->add('allowBonusCreditMultiple', 'checkbox', [
                'label'    => 'The user benefit from the same offer type more than once',
                'required' => false
            ])
            ->end();


        $formMapper->add('status', 'choice', [
            'choices' => SubscriptionPack::STATUSES,
            'label'   => 'Pack Status'
        ]);


    }

    /**
     * @param FormBuilderInterface $formMapper
     * @param                      $fieldName
     * @param array                $fieldOptions
     */
    private function addBillingFrameworkField($formMapper,
                                              $fieldName,
                                              $fieldOptions = [])
    {


        $options = ['choice_label' => 'name',
                    'choice_attr'  => function ($val) {
                        $choice_attr = ($val instanceof Price) ? [
                            'data'          => $val->getTierId(),
                            'data-price'    => $val->getPriceWithTax() > 0 ? $val->getPriceWithTax() : $val->getValue(),
                            'data-currency' => $val->getCurrency(),
                        ] : ['data' => $val->getId()];
                        return $choice_attr;
                    },
                    'choice_value' => function ($val) {
                        if ($val instanceof Carrier) {
                            return $val->getName();
                        } else {
                            return $val;
                        }
                    }];

        $options += $fieldOptions;
        $formMapper->add($fieldName, 'choice', $options);
        $formMapper->add($fieldName . "Id", HiddenType::class, ['required' => false]);
    }

    public function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('name')
            ->add('renewStrategy', null, array(
                'editable' => false
            ))
            ->add('buyStrategy', null, array(
                'editable' => false
            ))
            ->add('carrier', null, array(
                'editable' => false
            ))
            ->add('unlimited', null, array(
                'editable' => false,
                'label'    => 'Unlimited Downloads'
            ))
            ->add('credits', null, array(
                'editable' => false,
                'label'    => 'Credits'
            ))
            ->add('periodicity', 'choice', array(
                'editable' => true,
                'choices'  => array_flip(SubscriptionPack::PERIODICITY),
            ))
            ->add('status', 'choice', array(
                'editable' => true,
                'choices'  => array_flip(SubscriptionPack::STATUSES),
            ));

    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {

        $carriers   = $this->subscriptionPackDataProvider->getCarriers();
        $tiers      = $this->subscriptionPackDataProvider->getTiers();
        $strategies = $this->subscriptionPackDataProvider->getBillingStrategies();


        $datagridMapper->add('name')
            ->add('country')
            ->add('carrier', null, [], 'choice', ['choices'      => $carriers,
                                                  'choice_label' => 'name',
                                                  'choice_value' => 'id'
            ])
            ->add('buyStrategy', null, [], 'choice', ['choices'      => $strategies,
                                                      'choice_label' => 'name',
                                                      'choice_value' => 'id'
            ])
            ->add('renewStrategy', null, [], 'choice', ['choices'      => $strategies,
                                                        'choice_label' => 'name',
                                                        'choice_value' => 'id'
            ])
            ->add('periodicity')
            ->add('tier', null, [], 'choice', ['choices'      => $tiers,
                                               'choice_label' => 'name',
                                               'choice_value' => 'id'
            ])
            ->add('status', null, ['label' => 'Subscription Pack Active'])
            ->add('preferredRenewalStart', 'doctrine_orm_datetime_range',
                array('field_type' => 'sonata_type_datetime_range_picker'))
            ->add('preferredRenewalEnd', 'doctrine_orm_datetime_range',
                array('field_type' => 'sonata_type_datetime_range_picker'));

    }


    public function getNewInstance()
    {
        /** @var SubscriptionPack $instance */
        $instance = parent::getNewInstance();
        $instance->setCustomRenewPeriod(0);
        $instance->setUnlimited(false);
        $instance->setCredits(0);
        return $instance;

    }


}
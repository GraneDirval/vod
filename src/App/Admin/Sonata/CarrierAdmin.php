<?php

namespace App\Admin\Sonata;

use App\Domain\Entity\Carrier;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\BooleanType;
use Sonata\Form\Type\EqualType;
use SubscriptionBundle\Service\CAPTool\DTO\CarrierLimiterData;
use SubscriptionBundle\Service\CAPTool\Limiter\LimiterDataConverter;
use SubscriptionBundle\Service\CAPTool\Limiter\LimiterDataExtractor;
use SubscriptionBundle\Service\CAPTool\Limiter\LimiterStorage;
use SubscriptionBundle\Service\CAPTool\Limiter\StorageKeyGenerator;
use SubscriptionBundle\Service\CAPTool\SubscriptionLimiter;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

/**
 * Class CarrierAdmin
 */
class CarrierAdmin extends AbstractAdmin
{
    /**
     * @var SubscriptionLimiter
     */
    private $subscriptionLimiter;

    /**
     * @var LimiterStorage
     */
    private $limiterDataStorage;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var StorageKeyGenerator
     */
    private $storageKeyGenerator;

    /**
     * CarrierAdmin constructor
     *
     * @param string $code
     * @param string $class
     * @param string $baseControllerName
     * @param SubscriptionLimiter $subscriptionLimiter
     * @param LimiterStorage $limiterDataStorage
     * @param StorageKeyGenerator $storageKeyGenerator
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        string $code,
        string $class,
        string $baseControllerName,
        SubscriptionLimiter $subscriptionLimiter,
        LimiterStorage $limiterDataStorage,
        StorageKeyGenerator $storageKeyGenerator,
        EntityManagerInterface $entityManager
    ) {
        $this->subscriptionLimiter = $subscriptionLimiter;
        $this->limiterDataStorage  = $limiterDataStorage;
        parent::__construct($code, $class, $baseControllerName);
        $this->storageKeyGenerator = $storageKeyGenerator;
        $this->code                = $code;
        $this->entityManager       = $entityManager;
    }

    /**
     * @param Carrier $object
     */
    public function preUpdate($object)
    {
        $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($object);

        if ($originalData['numberOfAllowedSubscriptionsByConstraint']
            !== $object->getNumberOfAllowedSubscriptionsByConstraint()
        ) {
            $object->setIsCapAlertDispatch(false);
        }
    }

    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('uuid')
            ->add('billingCarrierId')
            ->add('operatorId')
            ->add('name')
            ->add('countryCode')
            ->add('defaultLanguage')
            ->add('isp')
            ->add('published')
            ->add('isConfirmationClick')
            ->add('isConfirmationPopup')
            ->add('trialInitializer')
            ->add('trialPeriod')
            ->add('subscriptionPeriod')
            ->add('numberOfAllowedSubscriptionsByConstraint')
            ->add('redirectUrl')
            ->add('resubAllowed')
            ->add('isCampaignsOnPause')
            ->add('isUnlimitedSubscriptionAttemptsAllowed')
            ->add('subscribeAttempts')
            ->add('isLpOff')
            ->add('isClickableSubImage')
            ->add('trackAffiliateOnZeroCreditSub');
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('billingCarrierId')
            ->add('operatorId')
            ->add('name')
            ->add('countryCode')
            ->add('defaultLanguage', TextType::class)
            ->add('isp')
            ->add('published')
            ->add('isConfirmationClick')
            ->add('isConfirmationPopup')
            ->add('trialInitializer')
            ->add('trialPeriod')
            ->add('subscriptionPeriod')
            ->add('resubAllowed')
            ->add('isCampaignsOnPause')
            ->add('trackAffiliateOnZeroCreditSub')
            ->add('isLpOff')
            ->add('isClickableSubImage', null, [
                'label' => 'Clickable image'
            ])
            ->add('_action', null, [
                'actions' => [
                    'show'   => [],
                    'edit'   => [],
                    'delete' => [],
                ]
            ]);
    }

    /**
     * Creation form for carrier
     *
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('uuid', TextType::class, [
                'required' => false
            ])
            ->add('billingCarrierId')
            ->add('operatorId')
            ->add('name')
            ->add('countryCode')
            ->add('defaultLanguage')
            ->add('isp')
            ->add('published')
            ->add('isConfirmationClick')
            ->add('isConfirmationPopup')
            ->add('isLpOff', null, [
                'label' => 'Turn off LP showing',
                'help' => 'If consent page exist, then show it. Otherwise will try to subscribe'
            ])
            ->add('trialInitializer')
            ->add('trialPeriod')
            ->add('subscriptionPeriod')
            ->add('numberOfAllowedSubscriptionsByConstraint', IntegerType::class, ['attr' => ['min' => 0], 'required' => false,])
            ->add('redirectUrl', UrlType::class, ['required' => false])
            ->add('resubAllowed')
            ->add('isCampaignsOnPause')
            ->add('trackAffiliateOnZeroCreditSub')
            ->add('isUnlimitedSubscriptionAttemptsAllowed', null, [
                'attr' => ["class" => "unlimited-games"]
            ])
            ->add('subscribeAttempts', null, [
                'attr' => ["class" => "count-of-subs"]
            ])
            ->add('isClickableSubImage', null, [
                'label' => 'Clickable image'
            ]);
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        /** @var Carrier $subject */
        $subject = $this->getSubject();

        $key = $this->storageKeyGenerator->generateKey($subject);

        $pending = $this->limiterDataStorage->getPendingSubscriptionAmount($key);

        $finished = $this->limiterDataStorage->getFinishedSubscriptionAmount($key);

        $available = $pending + $finished;

        $subject->setCounter($available);

        $showMapper
            ->add('uuid')
            ->add('billingCarrierId')
            ->add('operatorId')
            ->add('name')
            ->add('countryCode')
            ->add('default_language')
            ->add('isp')
            ->add('published')
            ->add('isConfirmationClick')
            ->add('isConfirmationPopup')
            ->add('isLpOff', null, [
                'label' => 'Turn off LP showing',
                'help' => 'If consent page exist, then show it. Otherwise will try to subscribe'
            ])
            ->add('trialInitializer')
            ->add('trialPeriod')
            ->add('subscriptionPeriod')
            ->add('resubAllowed')
            ->add('trackAffiliateOnZeroCreditSub')
            ->add('isCampaignsOnPause')
            ->add('isUnlimitedSubscriptionAttemptsAllowed')
            ->add('subscribeAttempts')
            ->add('numberOfAllowedSubscriptionsByConstraint')
            ->add('counter')
            ->add('isCapAlertDispatch')
            ->add('isClickableSubImage', null, [
                'label' => 'Clickable image'
            ])
            ->add('flushDate')
            ->add('redirectUrl');
    }

    /**
     * @param RouteCollection $collection
     */
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->clearExcept(['list', 'edit', 'delete', 'show']);

        parent::configureRoutes($collection);
    }
}

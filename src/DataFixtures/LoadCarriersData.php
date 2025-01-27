<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 4/17/18
 * Time: 5:27 PM
 */

namespace DataFixtures;


use App\Domain\Entity\Carrier;
use CommonDataBundle\DataFixtures\LoadLanguagesData;
use DataFixtures\Utils\FixtureDataLoader;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class LoadCarriersData extends AbstractFixture implements ContainerAwareInterface, DependentFixtureInterface
{

    use ContainerAwareTrait;

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {

        $data = FixtureDataLoader::loadDataFromJSONFile('carriers.json');

        foreach ($data as $row) {

            $uuid                                    = $row['uuid'];
            $billingCarrierId                        = $row['billingCarrierId'];
            $operatorId                              = $row['operatorId'];
            $name                                    = $row['name'];
            $countryCode                             = $row['countryCode'];
            $isp                                     = $row['isp'];
            $published                               = $row['published'];
            $isOneClickFlow                          = $row['isOneClickFlow'] ?? 0;
            $trialInitializer                        = $row['trialInitializer'];
            $isCampaignsOnPause                      = $row['isCampaignsOnPause'];
            $subscribeAttempts                       = $row['subscribeAttempts'];
            $numberOfAllowedSubscriptionByConstraint = $row['numberOfAllowedSubscriptionsByConstraint'];
            $redirectUrl                             = $row['redirectUrl'];
            $isCapAlertDispatch                      = $row['isCapAlertDispatch'];
            $flushdate                               = $row['flushDate'];


            $defaultLanguageId = $row['defaultLanguage']['uuid'] ?? null;
            $carrier = new Carrier($uuid);
            $carrier->setBillingCarrierId($billingCarrierId);
            $carrier->setName($name);
            $carrier->setCountryCode($countryCode);
            $carrier->setIsp((string)$isp);
            $carrier->setPublished($published);
            $carrier->setIsOneClickFlow($isOneClickFlow);
            $carrier->setTrialInitializer($trialInitializer);
            $carrier->setOperatorId($operatorId);
            $carrier->setIsCampaignsOnPause($isCampaignsOnPause);
            $carrier->setSubscribeAttempts((int)$subscribeAttempts);
            $carrier->setNumberOfAllowedSubscriptionsByConstraint($numberOfAllowedSubscriptionByConstraint);
            $carrier->setRedirectUrl($redirectUrl);
            $carrier->setFlushDate($flushdate ? \DateTime::createFromFormat('Y-m-d', $flushdate) : null);
            $carrier->setUuid($uuid);
            $carrier->setIsCapAlertDispatch((bool)$isCapAlertDispatch);

            if ($defaultLanguageId) {
                $carrier->setDefaultLanguage($this->getReference(sprintf('language_%s', $defaultLanguageId)));
            }

            $manager->persist($carrier);

            $this->addReference(sprintf('carrier_%s', $uuid), $carrier);
            $this->addReference(sprintf('carrier_with_internal_id_%s', $billingCarrierId), $carrier);

        }

        $manager->flush();
    }

    /**
     * This method must return an array of fixtures classes
     * on which the implementing class depends on
     * @return array
     */
    function getDependencies()
    {
        return [LoadLanguagesData::class];
    }
}
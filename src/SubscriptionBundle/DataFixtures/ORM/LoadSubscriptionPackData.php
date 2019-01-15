<?php

namespace SubscriptionBundle\DataFixtures\ORM;

use DataFixtures\LoadCountriesData;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Playwing\DiffToolBundle\Utils\FixtureDataLoader;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use SubscriptionBundle\Entity\SubscriptionPack;

class LoadSubscriptionPackData extends AbstractFixture implements ContainerAwareInterface, DependentFixtureInterface
{
    use ContainerAwareTrait;

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     *
     * @throws \Exception
     */
    public function load(ObjectManager $manager)
    {
        $data = FixtureDataLoader::loadDataFromJSONFile(__DIR__ . '/Data/', 'subscription_packs.json');

        foreach ($data as $row) {
            $id                             = $row['id'];
            $country_uuid                   = $row['country_uuid'];
            $status                         = $row['status'];
            $name                           = $row['name'];
            $description                    = $row['description'];
            $carrier_uuid                   = $row['carrier_uuid'];
            $periodicity                    = $row['periodicity'];
            $custom_renew_period            = $row['custom_renew_period'];
            $grace_period                   = $row['grace_period'];
            $price                          = $row['price'];
            $currency                       = $row['currency'];
            $credits                             = $row['credits'];
            $unlimited_grace_period         = $row['unlimited_grace_period'];
            $preferred_renewal_start        = $row['preferred_renewal_start'];
            $preferred_renewal_end          = $row['preferred_renewal_end'];
            $welcome_sms_text               = $row['welcome_sms_text'];
            $renewal_sms_text               = $row['renewal_sms_text'];
            $unsubscribe_sms_text           = $row['unsubscribe_sms_text'];
            $buy_strategy_name              = $row['buy_strategy_name'];
            $buy_strategy_id                = $row['buy_strategy_id'];
            $renew_strategy_name            = $row['renew_strategy_name'];
            $renew_strategy_id              = $row['renew_strategy_id'];
            $unlimited                           = $row['unlimited'];
            $is_first_subscription_free     = $row['is_first_subscription_free'];
            $is_first_subscription_free_multiple = $row['is_first_subscription_free_multiple'];
            $allow_bonus_credit                  = $row['allow_bonus_credit'];
            $allow_bonus_credit_multiple         = $row['allow_bonus_credit_multiple'];
            $bonus_credit                        = $row['bonus_credit'];
            $provider_managed_subscriptions = $row['provider_managed_subscriptions'];
            $created                        = $row['created'];
            $updated                        = $row['updated'];
            $is_resub_allowed               = $row['is_resub_allowed'];
            $displayCurrency                = $row['display_currency'] ?? '';
            $tierId                         = $row['tier_id'];


            $pack = new SubscriptionPack($id);
            $this->addReference(sprintf('subscription_pack_%s', $id), $pack);
            $this->addReference(sprintf('subscription_pack_with_name_%s', $name), $pack);

            if ($status == SubscriptionPack::ACTIVE_SUBSCRIPTION_PACK) {
                $this->addReference(sprintf('subscription_pack_for_carrier_%s', $carrier_uuid), $pack);
            }

            $pack->setCountry($this->getReference(sprintf('country_%s', $country_uuid)));
            $pack->setStatus($status);
            $pack->setName($name);
            $pack->setDescription($description);
            $pack->setCarrier($this->getReference(sprintf('carrier_%s', $carrier_uuid)));
            $pack->setPrice($price);
            $pack->setCurrency($currency);
            $pack->setCredits($credits);
            $pack->setPeriodicity($periodicity);
            $pack->setCustomRenewPeriod($custom_renew_period);
            $pack->setGracePeriod($grace_period);
            $pack->setUnlimitedGracePeriod($unlimited_grace_period);
            $pack->setPreferredRenewalStart(new \DateTime($preferred_renewal_start));
            $pack->setPreferredRenewalEnd(new \DateTime($preferred_renewal_end));
            $pack->setWelcomeSMSText($welcome_sms_text);
            $pack->setRenewalSMSText($renewal_sms_text);
            $pack->setUnsubscribeSMSText($unsubscribe_sms_text);
            $pack->setBuyStrategy($buy_strategy_name);
            $pack->setBuyStrategyId($buy_strategy_id);
            $pack->setRenewStrategy($renew_strategy_name);
            $pack->setRenewStrategyId($renew_strategy_id);
            $pack->setUnlimited($unlimited);
            $pack->setFirstSubscriptionPeriodIsFree($is_first_subscription_free);
            $pack->setFirstSubscriptionPeriodIsFreeMultiple($is_first_subscription_free_multiple);
            $pack->setAllowBonusCredit($allow_bonus_credit);
            $pack->setAllowBonusCreditMultiple($allow_bonus_credit_multiple);
            $pack->setBonusCredit($bonus_credit);
            $pack->setProviderManagedSubscriptions($provider_managed_subscriptions);
            $pack->setCreated(new \DateTime($created));
            $pack->setUpdated(new \DateTime($updated));
            $pack->setIsResubAllowed($is_resub_allowed);
            $pack->setDisplayCurrency($displayCurrency);
            $pack->setTierId((int)$tierId);

            $manager->persist($pack);
        }

        $manager->flush();
    }


    /**
     * This method must return an array of fixtures classes
     * on which the implementing class depends on
     *
     * @return array
     */
    function getDependencies()
    {
        return [LoadCountriesData::class];
    }
}
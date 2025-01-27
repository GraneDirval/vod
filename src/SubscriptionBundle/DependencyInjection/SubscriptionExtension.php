<?php
/**
 * Created by IntelliJ IDEA.
 * User: bharatm
 * Date: 25/07/17
 * Time: 10:32 AM
 */

namespace SubscriptionBundle\DependencyInjection;


use ExtrasBundle\Config\DefinitionReplacer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class SubscriptionExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    /**
     * Configures the passed container according to the merged configuration.
     *
     * @param array            $mergedConfig
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yml');
        $loader->load('repositories.yml');
        $loader->load('fixtures.yml');

        $loader->load('subscription-pack.yml');


        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config/carriers')
        );

        foreach (glob(__DIR__ . '/../Resources/config/carriers/*.yml') as $file) {
            $loader->load(basename($file));
        }

        if ($container->getParameter('kernel.environment') == 'test') {
            $loader = new YamlFileLoader(
                $container,
                new FileLocator(__DIR__ . '/../Resources/config/test')
            );

            foreach (glob(__DIR__ . '/../Resources/config/test/*.yml') as $file) {
                $loader->load(basename($file));
            }
        }

        $definition = $container->getDefinition('SubscriptionBundle\CAPTool\Subscription\Notificaton\EmailProvider');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['cap_tool']['notification']['mail_to'], '_cap_notification_mail_to_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['cap_tool']['notification']['mail_from'], '_cap_notification_mail_from_placeholder_');

        $definition = $container->getDefinition('SubscriptionBundle\BillingFramework\BillingOptionsProvider');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['billing_framework']['api_host'], '_billing_api_host_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['billing_framework']['client_id'], '_client_id_placeholder_');

        $definition = $container->getDefinition('SubscriptionBundle\ReportingTool\ReportingToolRequestSender');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['reporting_tool']['api_host'], '_reporting_stats_api_host_placeholder_');

        $definition = $container->getDefinition('SubscriptionBundle\Subscription\Common\RouteProvider');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['resub_not_allowed_route'], '_resub_not_allowed_route_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['action_not_allowed_url'], '_action_not_allowed_url_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['callback_host'], '_callback_host_placeholder_');

        $definition = $container->getDefinition('SubscriptionBundle\Piwik\Senders\RabbitMQ');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['event_tracking']['rabbit_mq']['host'], '_rabbitmq_host_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['event_tracking']['rabbit_mq']['port'], '_rabbitmq_port_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['event_tracking']['rabbit_mq']['user'], '_rabbitmq_user_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['event_tracking']['rabbit_mq']['password'], '_rabbitmq_password_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['event_tracking']['rabbit_mq']['vhost'], '_rabbitmq_vhost_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['event_tracking']['rabbit_mq']['exchange_name'], '_rabbitmq_exchange_name_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['event_tracking']['rabbit_mq']['queue_name'], '_rabbitmq_queue_name_placeholder_');

        $definition = $container->getDefinition('SubscriptionBundle\DataFixtures\ORM\LoadSubscriptionPackData');
        DefinitionReplacer::replacePlaceholder($definition, new Reference($mergedConfig['fixtures']['carrier_fixture']), '_carrier_fixture_service_placeholder_');

        $definition = $container->getDefinition('SubscriptionBundle\Affiliate\CampaignConfirmation\Google\Service\GoogleCredentialsProvider');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['campaign_confirmation']['google']['client_id'], '_client_id_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['campaign_confirmation']['google']['client_key'], '_client_secret_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['campaign_confirmation']['google']['refresh_token'], '_refresh_token_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['campaign_confirmation']['google']['developer_token'], '_developer_token_placeholder_');
        DefinitionReplacer::replacePlaceholder($definition, $mergedConfig['campaign_confirmation']['google']['client_customer_id'], '_client_customer_id_placeholder_');


    }


    /**
     * Allow an extension to prepend the extension configurations.
     */
    public function prepend(ContainerBuilder $container)
    {
        $identificationAdminPath = realpath(__DIR__ . '/../Resources/views/Admin');

        $container->loadFromExtension('twig', array(
            'paths' => array(
                $identificationAdminPath => 'SubscriptionAdmin',
            ),
        ));
    }
}
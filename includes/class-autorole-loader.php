<?php

/**
 * Register all actions and filters for the plugin
 *
 * @link       https://christiandalton.me
 * @since      1.0.0
 *
 * @package    Autorole
 * @subpackage Autorole/includes
 */

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    Autorole
 * @subpackage Autorole/includes
 * @author     Christian Dalton <support@autorole.io>
 */
class Autorole_Loader
{

    /**
     * The array of actions registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array $actions The actions registered with WordPress to fire when the plugin loads.
     */
    protected $actions;

    /**
     * The array of filters registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array $filters The filters registered with WordPress to fire when the plugin loads.
     */
    protected $filters;

    /**
     * Initialize the collections used to maintain the actions and filters.
     *
     * @since    1.0.0
     */
    public function __construct()
    {

        $this->actions = array();
        $this->filters = array();

    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @param string $hook The name of the WordPress action that is being registered.
     * @param object $component A reference to the instance of the object on which the action is defined.
     * @param string $callback The name of the function definition on the $component.
     * @param int $priority Optional. The priority at which the function should be fired. Default is 10.
     * @param int $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
     *
     * @since    1.0.0
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @param string $hook The name of the WordPress filter that is being registered.
     * @param object $component A reference to the instance of the object on which the filter is defined.
     * @param string $callback The name of the function definition on the $component.
     * @param int $priority Optional. The priority at which the function should be fired. Default is 10.
     * @param int $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1
     *
     * @since    1.0.0
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * A utility function that is used to register the actions and hooks into a single
     * collection.
     *
     * @param array $hooks The collection of hooks that is being registered (that is, actions or filters).
     * @param string $hook The name of the WordPress filter that is being registered.
     * @param object $component A reference to the instance of the object on which the filter is defined.
     * @param string $callback The name of the function definition on the $component.
     * @param int $priority The priority at which the function should be fired.
     * @param int $accepted_args The number of arguments that should be passed to the $callback.
     *
     * @return   array                                  The collection of actions and filters registered with WordPress.
     * @since    1.0.0
     * @access   private
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args)
    {

        $hooks[] = array(
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;

    }

    /**
     * Register the filters and actions with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {

//		 Disable async webhooks
        function custom_woocommerce_disable_async_webhook(): bool
        {
            return false;
        }

        add_filter('woocommerce_webhook_deliver_async', 'custom_woocommerce_disable_async_webhook');


        function test_install($data): string
        {
            $site_url = get_site_url();
            return json_encode((object)['siteUrl' => $site_url, 'version' => AUTOROLE_VERSION]);
        }

        add_action('rest_api_init', function () {
            register_rest_route('autorole/v1', '/testInstall', array(
                'methods' => 'GET',
                'callback' => 'test_install',
                'permission_callback' => '__return_true'
            ));
        });

        function redirect_to_autorole(): void
        {
            if (is_wc_endpoint_url('order-received')) {
                global $wp;

                //	Find Order Id Regex
                $re = '/(?<=order-received\/)(\d+)/m';

                // Get the order ID
                preg_match_all($re, $wp->request, $matches, PREG_SET_ORDER, 0);

                $order_id = $matches[0][0];

                $subscriptions = wcs_get_subscriptions_for_order($order_id, array('order_type' => 'any'));

                $data = [];

                foreach ($subscriptions as $subscription) {
                    $order_key_id = $subscription->get_data();
                    $data[] = $order_key_id;
                }

                $site_url = get_site_url();

                $url = 'https://api.autorole.io/woo/redirectRequest';

                $finalData = (object)['siteUrl' => $site_url, 'data' => $data, 'version' => AUTOROLE_VERSION];

                $resp = wp_remote_post($url, [
                    'method' => 'POST',
                    'timeout' => 30,
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => json_encode($finalData)

                ]);

                wp_redirect(json_decode(wp_remote_retrieve_body($resp)));
                exit();
            }
        }

        add_action('template_redirect', 'redirect_to_autorole');
    }

}

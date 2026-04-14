<?php


function wpqb_plugin_has_active_required_plugins($plugin_name = '')
{
    $plugins['woocommerce'] = 'woocommerce/woocommerce.php';

    $_is_valid = [];
    foreach ($plugins as $ins => $plugin) {
        $_is_valid[] = is_plugin_active($plugin) ? 'yes' : 'no';
    }
    if (!empty($plugin_name)) {
        if (!empty($plugins[$plugin_name])) {
            return is_plugin_active($plugins[$plugin_name]) ? true : false;
        } else {
            return is_plugin_active($plugin_name) ? true : false;
        }
    }
    if (in_array('no', $_is_valid)) {
        return false;
    }
    return true;
}

function wpqb_plugin_plugin_admin_notce()
{
    $messages = [];
    if (!wpqb_plugin_has_active_required_plugins('woocommerce')) {
        $messages[] = [
            'status' => 'error',
            'message' => __(wpqb_plugin_info['Name'] . " requires <b>WooCommerce</b> to be installed and active.", 'wpqb_plugin'),
        ];
    }

    ob_start();
    if (!empty($messages)):
        foreach ($messages as $message):
            $extra = !empty($message['extra']) ? $message['extra'] : ''; //is-dismissible
            echo '<div class="notice notice-' . $message['status'] . ' ' . $extra . '">';
            echo '<p>';
            echo '<strong>' . strtoupper($message['status']) . ': </strong>';
            echo $message['message'];
            echo '</p>';
            echo '</div>';
        endforeach;
    endif;
    $messages_html = ob_get_clean();
    echo $messages_html;
}

if (wpqb_plugin_has_active_required_plugins()):

    function wpqb_plugin_get_template($template_name, $args = [], $template_path = '', $default_path = '')
    {
        if (!empty($args) && is_array($args)) {
            extract($args);
        }

        if (!$template_path) {
            $template_path = wpqb_plugin_info['slug'] . '/';
        }

        if (!$default_path) {
            $default_path = wpqb_plugin_template_path;
        }

        $template_name = (strpos($template_name, '.php') > -1) ? $template_name : $template_name . '.php';

        $template = locate_template([$template_path . $template_name]);
        if (!$template) {
            $template = $default_path . $template_name;
        }

        if (file_exists($template)) {
            include $template;
        }
    }

    function wpqb_plugin_settings($data = [])
    {
        if (!empty($data)) {
            update_option('wpqb_plugin_setting', $data);
        }
        $settings = get_option('wpqb_plugin_setting', []);
        return $settings;
    }

    function wpqb_plugin_logs($data, $log = 'reports')
    {
        $fp = fopen(get_home_path() . 'wpqb_plugin-' . $log . '.log', 'a+');
        fwrite($fp, date('Y-m-d H:i:s') . ' ' . $data . "\r\n");
        fclose($fp);
        echo "$data" . PHP_EOL;
    }


endif;

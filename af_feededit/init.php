<?php

// https://github.com/fastcat/tt-rss-af-feededit
// Inspiration from and thanks to https://github.com/mbirth/ttrss_plugin-af_feedmod

class Af_Feededit extends Plugin implements IHandler
{
    private $host;

    function about()
    {
        return array(
            1.0,   // version
            'Munge article contents using regexes',   // description
            'cheetah@fastcat.org',   // author
            false,   // is_system
        );
    }

    function api_version()
    {
        return 2;
    }

    function init($host)
    {
        $this->host = $host;
        $host->add_hook($host::HOOK_PREFS_TABS, $this);
        $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
    }

    function csrf_ignore($method)
    {
        $csrf_ignored = array("index", "edit");
        return array_search($method, $csrf_ignored) !== false;
    }

    function before($method)
    {
        if ($_SESSION["uid"]) {
            return true;
        }
        return false;
    }

    function after()
    {
        return true;
    }

    function hook_article_filter($article)
    {
        $json_conf = $this->host->get($this, 'json_conf');
        $owner_uid = $article['owner_uid'];
        $data = json_decode($json_conf, true);

        if (!is_array($data)) {
            // no valid JSON or no configuration at all
            return $article;
        }

        foreach ($data as $urlpart=>$config) {
            if (strpos($article['feed_url'], $urlpart) === false) {
                continue;
            }
            if (!isset($config['field'])) {
                continue;
            }
            if (!isset($article[$config['field']])) {
                continue;
            }
            $field_value = $article[$config['field']];

            switch ($config['type']) {
                case 'regex':
                    // optional check regex before running the search & replace
                    if (isset($config['check'])) {
                        if (!preg_match($config['check'], $field_value)) {
                            continue;
                        }
                    }
                    if (!isset($config['search'] or !isset($config['replace'])) {
                        // Missing settings
                        continue;
                    }
                    // optional replace limit
                    if (isset($config['limit'])) {
                        $limit = $config['limit'];
                    } else {
                        $limit = -1;
                    }
                    $replaced = preg_replace($config['search'], $config['replace'], $field_value);
                    if ($replaced == NULL) {
                        // error
                        continue;
                    }
                    $field_value = $replaced;
                    break;
                
                // TODO: case 'php' or some such fancyness

                default:
                    // unknown type or invalid config
                    continue;
            }
            
            // save edited value back
            $article[$config['field']] = $field_value;
            
            // only process the first matching config entry
            break;
        }

        return $article;
    }

    function hook_prefs_tabs($args)
    {
        print '<div id="feededitConfigTab" dojoType="dijit.layout.ContentPane"
            href="backend.php?op=af_feededit"
            title="' . __('FeedEdit') . '"></div>';
    }

    function index()
    {
        $pluginhost = PluginHost::getInstance();
        $json_conf = $pluginhost->get($this, 'json_conf');

        print "<form dojoType=\"dijit.form.Form\">";

        print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
            evt.preventDefault();
            if (this.validate()) {
                new Ajax.Request('backend.php', {
                    parameters: dojo.objectToQuery(this.getValues()),
                    onComplete: function(transport) {
                        if (transport.responseText.indexOf('error') >= 0) {
                            notify_error(transport.responseText);
                        } else {
                            notify_info(transport.responseText);
                        }
                    }
                });
            }
            </script>";

        print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"af_feededit\">";

        print "<table width='100%'><tr><td>";
        print "<textarea dojoType=\"dijit.form.SimpleTextarea\" name=\"json_conf\" style=\"font-size: 12px; width: 99%; height: 500px;\">$json_conf</textarea>";
        print "</td></tr></table>";

        print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".__("Save")."</button>";

        print "</form>";
    }

    function save()
    {
        $json_conf = $_POST['json_conf'];

        if (is_null(json_decode($json_conf))) {
            echo __("error: Invalid JSON!");
            return false;
        }

        $this->host->set($this, 'json_conf', $json_conf);
        echo __("Configuration saved.");
    }

}

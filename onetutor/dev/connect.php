<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(['url' => '', 'code' => '']);
\core\session\manager::set_user(get_admin());
\local_onetutor\provisioner::connect($options['url'], $options['code']);
cli_writeln('Connected as site ' . \local_onetutor\connection::site_id());

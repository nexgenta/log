This module provides logging services to Eregansu applications.

Check out git://github.com/nexgenta/log.git into your app/ directory.

To configure, you will need to define LOG_IRI in your config.php, and add
the following to your appconfig.php:

	$SETUP_MODULES[] = array('name' => 'log', 'file' => 'module.php', 'class' => 'LogModule');
	$CLI_ROUTES['logger'] = array('class' => 'LoggerCLI', 'name' => 'log', 'file' => 'cli.php', 'description' => 'Write a message to the system log');

Once done, you can run './eregansu setup' to update the database schema.

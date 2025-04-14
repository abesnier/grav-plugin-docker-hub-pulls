<?php

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;

/**
 * Class DockerHubPullsPlugin
 * @package Grav\Plugin
 */
class DockerHubPullsPlugin extends Plugin
{
	const URL = "https://hub.docker.com/v2/repositories/";
	protected $pulls;

	/**
	 * @return array
	 *
	 * The getSubscribedEvents() gives the core a list of events
	 *     that the plugin wants to listen to. The key of each
	 *     array section is the event that the plugin listens to
	 *     and the value (in the form of an array) contains the
	 *     callable (or function) as well as the priority. The
	 *     higher the number the higher the priority.
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onPluginsInitialized' => [
				// Uncomment following line when plugin requires Grav < 1.7
				// ['autoload', 100000],
				['onPluginsInitialized', 0]
			]
		];
	}


	/**
	 * [PluginsLoadedEvent:100000] Composer autoload.
	 *
	 * @return ClassLoader
	 */
	public function autoload(): ClassLoader
	{
		return require __DIR__ . '/vendor/autoload.php';
	}




	/**
	 * Initialize the plugin
	 */
	public function onPluginsInitialized(): void
	{
		// Don't proceed if we are in the admin plugin
		if ($this->isAdmin()) {
			return;
		}

		if ($this->config->get('plugins.docker-hub-pulls.enabled')) {
			// Enable the main events we are interested in
			$this->enable([
				// Put your main events here
				'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
				'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
			]);
		}
	}

	/**
	 * Add current directory to twig lookup paths.
	 *
	 * @return void
	 */
	public function onTwigTemplatePaths()
	{
		$this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
	}


	/**
	 * Set needed variables to display the taxonomy list.
	 *
	 * @return void
	 */

	public function onTwigSiteVariables()
	{
		$twig = $this->grav['twig'];
		//$twig->twig_vars['dockeruser'] = $this->config->get('plugins.docker-hub-pulls.username');
		$twig->twig_vars['dockerpulls'] = new DockerHubPulls();
	}
}

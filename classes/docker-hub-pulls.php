<?php

namespace Grav\Plugin;

use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;

class DockerHubPulls
{

	const URL = "https://hub.docker.com/v2/repositories/";
	protected $pulls;

	/**
	 *
	 * @return array An array of array containing the image name, its description, and its pull count
	 */
	public function getPulls()
	{
		if (empty($this->getUser())) {
			return  null;
		}
		// gets the configuration
		$imgs = Grav::instance()['config']->get('plugins.docker-hub-pulls.images');

		// if no image name is provided, get all the images of the user
		if (!$imgs) return $this->getAllPullsAtOnce(Grav::instance()['config']->get('plugins.docker-hub-pulls.username'));

		// if image names are provided, iterate over and get the individual pull counts
		foreach ($imgs  as $image) {
			$this->pulls[] = $this->getPullsByImage(Grav::instance()['config']->get('plugins.docker-hub-pulls.username'), $image);
		}
		return $this->orderResults($this->pulls);
	}


        /**
         *
         *@return string the username as setup in the configuration
         */
	public function getUser()
	{
		return Grav::instance()['config']->get('plugins.docker-hub-pulls.username');
	}


	// If we query https://hub.docker.com/v2/repositories/username/, this will return all the images (with some caveats) uploaded by the user.
	// This function is called if the user has not specified any image in the plugin configuration
	protected function getAllPullsAtOnce($username)
	{
		try {
			$url = self::URL . $username . "?page_size=" . $this->getImagesCount($username);  // check how many images the user has uploaded, and use it as base for api query
			$str = file_get_contents($url);
			$json = json_decode($str, true);
			foreach ($json['results'] as $result) {
				$this->pulls[] = array("name" => $result['name'], "count" => $result['pull_count'], "desc" => $result['description']);
			}

			return $this->orderResults($this->pulls);
		} catch (\Exception $e) {
			return $this->pulls[] = array("error", "error");
		}
	}


	// By default, Docker Hub api returns 10 images, but it can be overriden and the results are paginated 100 by 100
	// So we make a first query to read the count of images and use it in the function getAllPullsAtOnce.
	// The API returns a json object where we can read the key 'count'.
	protected function getImagesCount($username)
	{
		if (Grav::instance()['config']->get('plugins.docker-hub-pulls.limit')) return Grav::instance()['config']->get('plugins.docker-hub-pulls.limit');
		try {
			$url = self::URL . $username;
			$str = file_get_contents($url);
			$json = json_decode($str, true);
			return $json['count'];
		} catch (\Exception $e) {
			return 20;
		} //because I needed a limit...
	}


	// Makes a simple query based on the template https://hub.docker.com/v2/repositories/username/image
	// The API returns a json object we can read.
	protected function getPullsByImage($username, $image)
	{
		try {
			$url = self::URL . $username . "/" . $image;
			$str = file_get_contents($url);
			$json = json_decode($str, true);
			return array("name" => $image, "count" => $json['pull_count'], "desc" => $json['description']);
		} catch (\Exception $e) {
			return array("error", "error");
		}
	}


	// Order the results if this is set in the plugin configuration
	// Because the results are an array of arrays, custom comparison functions are required. See below.
	protected function orderResults($a)
	{
		$o = Grav::instance()['config']->get('plugins.docker-hub-pulls.orderby');
		switch ($o) {
			case "none":
				return $a;
				break;
			case "name":
				usort($a, array($this, "compareName"));
				return $a;
				break;
			case "pulls":
				usort($a, array($this, "comparePulls"));
				return $a;
				break;
		}
	}


	// Compare the pull counts of objects.
	// Returns in descending order
	protected function comparePulls($a, $b)
	{
		if ($a["count"] == $b["count"]) {
			return 0;
		}
		return ($a["count"] < $b["count"]) ? 1 : -1;
	}


	// Compare the image name of objects.
	// Returns in ascending order
	protected function compareName($a, $b)
	{
		return strcmp($a["name"], $b["name"]);
	}
}

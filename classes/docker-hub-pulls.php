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

	/**
	 * Retrieves all Docker Hub repository pulls for a given username in a single API call.
	 * 
	 * This function queries the Docker Hub API to fetch all images uploaded by the specified user.
	 * It calculates the total number of images uploaded by the user and uses it as the page size
	 * for the API request. The results are then processed and stored in the `$pulls` property.
	 * 
	 * @param string $username The Docker Hub username whose repository pulls are to be retrieved.
	 * 
	 * @return array An array of repository pull data, each containing:
	 *               - "name" (string): The name of the repository.
	 *               - "count" (int): The pull count of the repository.
	 *               - "desc" (string|null): The description of the repository.
	 *               If an error occurs, an array with a single entry ["error", "error"] is returned.
	 * 
	 * @throws \Exception If an error occurs during the API request or data processing.
	 */
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

	/**
	 * Retrieves the total count of images for a given Docker Hub username.
	 *
	 * This function makes an API call to Docker Hub to fetch the count of images
	 * associated with the specified username. If a limit is configured in the plugin
	 * settings, it will return that limit instead of making the API call. In case of
	 * an error during the API call, a default value of 20 is returned.
	 *
	 * @param string $username The Docker Hub username for which to retrieve the image count.
	 * @return int The total count of images or the configured limit.
	 */
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



	/**
	 * Retrieves pull count and description for a specific Docker Hub image.
	 *
	 * @param string $username The Docker Hub username associated with the image.
	 * @param string $image The name of the Docker Hub image.
	 * 
	 * @return array An associative array containing:
	 *               - "name" (string): The name of the image.
	 *               - "count" (int): The pull count of the image (default is 0 if not available).
	 *               - "desc" (string): The description of the image (default is "No description available" if not provided).
	 *               - If an error occurs, returns an array with two "error" strings.
	 */
	protected function getPullsByImage($username, $image)
	{
		try {
			$url = self::URL . $username . "/" . $image;
			$str = file_get_contents($url);
			$json = json_decode($str, true);
			return array(
				"name" => $image,
				"count" => isset($json['pull_count']) ? $json['pull_count'] : 0,
				"desc" => isset($json['description']) ? $json['description'] : "No description available"
			);
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
		return $b["count"] <=> $a["count"];
	}


	// Compare the image name of objects.
	// Returns in ascending order
	protected function compareName($a, $b)
	{
		// Perform a case-sensitive alphabetical comparison of image names
		return strcmp($a["name"], $b["name"]);
	}
}

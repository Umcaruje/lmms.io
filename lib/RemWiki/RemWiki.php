<?php

namespace RemWiki;

require_once($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');
use Gaufrette\Filesystem;
use Gaufrette\Adapter\Local as LocalAdapter;
use Gaufrette\File;
use GuzzleHttp\Client;

/**
 * Helper class for getting rendered pages from a remote MediaWiki instance
 */
class RemWiki
{
	/**
	 * Set up the remote wiki.
	 *
	 * @param string $url URL of the wiki instance. $url/api.php should
	 * be present.
	 */
	public function __construct($url)
	{
		if (substr($url, -1) != '/') {
			$url = $url . '/';
		}
		$this->url = $url;

		$this->wikipath = parse_url($url, PHP_URL_PATH);
		if ($this->wikipath == false) {
			throw new Exception('Invalid wiki URL');
		}

		$this->client = new Client([
			'base_url' => $url
		]);

		$adapter = new LocalAdapter('/tmp/wiki', true);
		$this->fs = new Filesystem($adapter);
	}

	private function api($query)
	{
		$query['format'] = 'json';
		return $this->client->get('/wiki/api.php', [
			'query' => $query
		]);
	}

	private function cacheFile($page)
	{
		return new File($page . '.html', $this->fs);
	}

	private function revFile($page)
	{
		return new File($page . '.rev', $this->fs);
	}

	private function requestRev($page)
	{
		$response = $this->api([
			'action' => 'query',
			'prop' => 'info',
			'titles' => $page
		]);

		if ($response) {
			return reset($response->json()['query']['pages'])['lastrevid'];
		}
	}

	private function requestParse($page)
	{
		$response = $this->api([
			'action' => 'parse',
			'page' => $page
		]);

		$json = $response->json();

		// Fix relative links in rendered HTML
		$html = $json['parse']['text']['*'];

		// Get the wiki's relative path on its server
		// e.g. 'http://lmms.sf.net/wiki/' -> '/wiki/'
		$path_escaped = preg_replace('/\//', '\/', $this->wikipath);

		// Fix links
		$html = preg_replace(
			[
				// Internal links to wiki pages
				'/"'.$path_escaped.'index.php\/?(\?title=)?(.+?)"/m',
				// Links to other resources like images
				'/"'.$path_escaped.'(.+?)"/m',
				// Thumbnails
				'/class="thumbimage"/m',
			],
			[
				'/documentation/$2',
				$this->url.'$1',
				'class="img-thumbnail"',
			],
			$html
		);

		$json['parse']['text']['*'] = $html;

		return $json['parse'];
	}

	public function isInCache($page)
	{
		return $this->revFile($page)->exists() && $this->cacheFile($page)->exists();
	}

	public function hasNewerRemote($page, $maxage = 60)
	{
		if (! $this->isInCache($page)) {
			return true;
		}

		$revfile = $this->revFile($page);
		$cachefile = $this->cacheFile($page);

		// Don't check for newer revisions more often than every $maxage seconds
		if (time() - $revfile->getMtime() < $maxage) {
			return false;
		}

		$localrev = intval($revfile->getContent());
		$remoterev = $this->requestRev($page);

		if ($remoterev == $localrev) {
			return false;
		} else {
			return $remoterev;
		}
	}

	public function parse($page)
	{
		$revfile = $this->revFile($page);
		$cachefile = $this->cacheFile($page);

		// Can we get the page from cache?
		$remoterev = $this->hasNewerRemote($page);
		if ($remoterev === false) {
			return json_decode($cachefile->getContent(), $assoc=true);
		} else {
			$json = $this->requestParse($page);
			$cachefile->setContent(json_encode($json));
			$revfile->setContent($remoterev);

			return $json;
		}
	}

	private $url;
	private $wikipath;
	private $fs;
	private $client;
}

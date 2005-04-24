<?php
/**
 * PEAR_REST_10
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   pear
 * @package    PEAR
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2005 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/PEAR
 * @since      File available since Release 1.4.0a12
 */

/**
 * For downloading REST xml/txt files
 */
require_once 'PEAR/REST.php';

/**
 * Implement REST 1.0
 *
 * @category   pear
 * @package    PEAR
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2005 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/PEAR
 * @since      Class available since Release 1.4.0a12
 */
class PEAR_REST_10 extends PEAR_REST
{
    function getDownloadURL($base, $packageinfo, $prefstate, $installed)
    {
        $channel = $packageinfo['channel'];
        $package = $packageinfo['package'];
        $states = $this->betterStates($prefstate, true);
        if (!$states) {
            return PEAR::raiseError('"' . $prefstate . '" is not a valid state');
        }
        $state = $version = null;
        if (isset($packageinfo['state'])) {
            $state = $packageinfo['state'];
        }
        if (isset($packageinfo['version'])) {
            $version = $packageinfo['version'];
        }
        $info = $this->retrieveData($base . 'r/' . strtolower($package) . '/allreleases.xml');
        if (PEAR::isError($info)) {
            return $info;
        }
        if (!isset($info['r'])) {
            return false;
        }
        $found = false;
        $release = false;
        if (!is_array($info['r'])) {
            $info['r'] = array($info['r']);
        }
        foreach ($info['r'] as $release) {
            if ($installed && version_compare($release['v'], $installed, '<')) {
                continue;
            }
            if (isset($state)) {
                if ($release['s'] == $state) {
                    $found = true;
                    break;
                }
            } elseif (isset($version)) {
                if ($release['v'] == $version) {
                    $found = true;
                    break;
                }
            } else {
                if (in_array($release['s'], $states)) {
                    $found = true;
                    break;
                }
            }
        }
        return $this->_returnDownloadURL($base, $package, $release, $info, $found);
    }

    function getDepDownloadURL($base, $xsdversion, $dependency, $deppackage,
                               $prefstate = 'stable', $installed = false)
    {
        $channel = $dependency['channel'];
        $package = $dependency['name'];
        $states = $this->betterStates($prefstate, true);
        if (!$states) {
            return PEAR::raiseError('"' . $prefstate . '" is not a valid state');
        }
        $state = $version = null;
        if (isset($packageinfo['state'])) {
            $state = $packageinfo['state'];
        }
        if (isset($packageinfo['version'])) {
            $version = $packageinfo['version'];
        }
        $info = $this->retrieveData($base . 'r/' . strtolower($package) . '/allreleases.xml');
        if (PEAR::isError($info)) {
            return $info;
        }
        if (!isset($info['r'])) {
            return false;
        }
        $exclude = array();
        $min = $max = $recommended = false;
        if ($xsdversion == '1.0') {
            $pinfo['package'] = $dependency['name'];
            $pinfo['channel'] = 'pear.php.net'; // this is always true - don't change this
            switch ($dependency['rel']) {
                case 'ge' :
                    $min = $dependency['version'];
                break;
                case 'gt' :
                    $min = $dependency['version'];
                    $exclude = array($dependency['version']);
                break;
                case 'eq' :
                    $recommended = $dependency['version'];
                break;
                case 'lt' :
                    $max = $dependency['version'];
                    $exclude = array($dependency['version']);
                break;
                case 'le' :
                    $max = $dependency['version'];
                break;
                case 'ne' :
                    $exclude = array($dependency['version']);
                break;
            }
        } else {
            $pinfo['package'] = $dependency['name'];
            $min = isset($dependency['min']) ? $dependency['min'] : false;
            $max = isset($dependency['max']) ? $dependency['max'] : false;
            $recommended = isset($dependency['recommended']) ?
                $dependency['recommended'] : false;
            if (isset($dependency['exclude'])) {
                if (!isset($dependency['exclude'][0])) {
                    $exclude = array($dependency['exclude']);
                }
            }
        }
        $found = false;
        $release = false;
        if (!is_array($info['r'])) {
            $info['r'] = array($info['r']);
        }
        foreach ($info['r'] as $release) {
            if ($installed && version_compare($release['v'], $installed, '<')) {
                continue;
            }
            if (in_array($release['v'], $exclude)) { // skip excluded versions
                continue;
            }
            // allow newer releases to say "I'm OK with the dependent package"
            if ($xsdversion == '2.0' && isset($release['c'])) {
                if (isset($release['c'][$deppackage['channel']]
                      [$deppackage['p']]) && in_array($release['v'],
                        $release['c'][$deppackage['channel']]
                        [$deppackage['package']])) {
                    $recommended = $release['v'];
                }
            }
            if ($recommended) {
                if ($release['v'] != $recommended) { // if we want a specific
                    // version, then skip all others
                    continue;
                } else {
                    if (!in_array($release['s'], $states)) {
                        // the stability is too low, but we must return the
                        // recommended version if possible
                    return $this->_returnDownloadURL($base, $package, $release, $info, true);
                    }
                }
            }
            if ($min && version_compare($release['v'], $min, 'lt')) { // skip too old versions
                continue;
            }
            if ($max && version_compare($release['v'], $max, 'gt')) { // skip too new versions
                continue;
            }
            if ($installed && version_compare($release['v'], $installed, '<')) {
                continue;
            }
            if (in_array($release['s'], $states)) { // if in the preferred state...
                $found = true; // ... then use it
                break;
            }
        }
        return $this->_returnDownloadURL($base, $package, $release, $info, $found);
    }

    function _returnDownloadURL($base, $package, $release, $info, $found)
    {
        if ($found) {
            $releaseinfo = $this->retrieveData($base . 'r/' . strtolower($package) . '/' . 
                $release['v'] . '.xml');
            if (PEAR::isError($releaseinfo)) {
                return $releaseinfo;
            }
            $packagexml = $this->retrieveData($base . 'r/' . strtolower($package) . '/' .
                'deps.' . $release['v'] . '.txt', false, true);
            if (PEAR::isError($packagexml)) {
                return $packagexml;
            }
            return 
                array('version' => $releaseinfo['v'],
                      'info' => unserialize($packagexml),
                      'package' => $releaseinfo['p']['_content'],
                      'url' => $releaseinfo['g']);
        } else {
            $release = $info['r'][0];
            $releaseinfo = $this->retrieveData($base . 'r/' . strtolower($package) . '/' . 
                $release['v'] . '.xml');
            if (PEAR::isError($releaseinfo)) {
                return $releaseinfo;
            }
            $packagexml = unserialize($this->retrieveData($base . 'r/' . strtolower($package) . '/' .
                'deps.' . $release['v'] . '.txt', false, true));
            if (PEAR::isError($packagexml)) {
                return $packagexml;
            }
            return
                array('version' => $releaseinfo['v'],
                      'package' => $releaseinfo['p']['_content'],
                      'info' => unserialize($packagexml));
        }
    }

    /**
     * Return an array containing all of the states that are more stable than
     * or equal to the passed in state
     *
     * @param string Release state
     * @param boolean Determines whether to include $state in the list
     * @return false|array False if $state is not a valid release state
     */
    function betterStates($state, $include = false)
    {
        static $states = array('snapshot', 'devel', 'alpha', 'beta', 'stable');
        $i = array_search($state, $states);
        if ($i === false) {
            return false;
        }
        if ($include) {
            $i--;
        }
        return array_slice($states, $i + 1);
    }
}
?>
<?php
/*
 * SettingsHook.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2021 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Plugins\Hooks;

use App\Models\User;
use Illuminate\Support\Str;

use App\Models\Device;
use Illuminate\Support\Facades\Auth;

abstract class PageHook
{
    /** @var string */
    public $view = 'resources.views.page';

    public function authorize(User $user): bool
    {
        return true;
    }

    public function data(): array
    {
	$devices = Device::hasAccess(Auth::user())
            ->where('os','asa')
            ->where('status',1)
            ->orderBy('hostname')
            ->get();

	$devices = $devices->map(function($item, $key) {
		$node_info = json_decode((new \App\ApiClients\Oxidized())->getContent('/node/show/' . $item->hostname . '?format=json'), true);
		$config = json_decode((new \App\ApiClients\Oxidized())->getContent('/node/fetch/' . $node_info['full_name'] . '?format=json'), true);
		$pattern = '/asdm image (.*?)\/asdm-(open)?jre-(\d+-\d+)\.bin/';
		preg_match($pattern, $config, $matches);
		if (count($matches) >= 4) {
			$extractedString = $matches[3];
			$parts = explode('-', $extractedString);
			$formattedString = $parts[0][0] . "." . $parts[0][1] . $parts[0][2] . '.' . $parts[0][3] . '.' . $parts[1];
		} else {
			$formattedString = "String not found";
		}
		$item->asdm_ver = $formattedString;

		// Initialize variables to keep track of the highest ending number and the selected version string
		$highestEndingNumber = -1;
		$selectedVersionString = '';

		// Use regular expression to find all matches of "anyconnect image" lines with "win"
		$pattern = '/anyconnect image (?:disk0:\/)?anyconnect-win-(\d+\.\d+\.\d+)-webdeploy-k9.pkg (\d+)/';
		preg_match_all($pattern, $config, $matches);

		// Iterate through the ending numbers and select the version string from the line with the highest ending number
		foreach ($matches[1] as $index => $versionString) {
			$endingNumber = (int)$matches[2][$index];
			if ($endingNumber > $highestEndingNumber) {
				$highestEndingNumber = $endingNumber;
				$selectedVersionString = $versionString;
			}
		}

        if (!empty($selectedVersionString)) {
            $item->anyconnectWin_ver = $selectedVersionString;
        } else {
            $item->anyconnectWin_ver = null;
        }

        // Initialize variables to keep track of the highest ending number and the selected version string
        $highestEndingNumber = -1;
        $selectedVersionString = '';

        // Use regular expression to find all matches of "anyconnect image" lines with "win"
        $pattern = '/anyconnect image (?:disk0:\/)?anyconnect-linux64-(\d+\.\d+\.\d+)-webdeploy-k9.pkg (\d+)/';
        preg_match_all($pattern, $config, $matches);

        // Iterate through the ending numbers and select the version string from the line with the highest ending number
        foreach ($matches[1] as $index => $versionString) {
            $endingNumber = (int)$matches[2][$index];
            
            if ($endingNumber > $highestEndingNumber) {
                $highestEndingNumber = $endingNumber;
                $selectedVersionString = $versionString;
            }
        }

        if (!empty($selectedVersionString)) {
            $item->anyconnectLin_ver = $selectedVersionString;
        } else {
            $item->anyconnectLin_ver = null;
        }

        // Initialize variables to keep track of the highest ending number and the selected version string
        $highestEndingNumber = -1;
        $selectedVersionString = '';

        // Use regular expression to find all matches of "anyconnect image" lines with "win"
        $pattern = '/anyconnect image (?:disk0:\/)?anyconnect-macos-(\d+\.\d+\.\d+)-webdeploy-k9.pkg (\d+)/';
        preg_match_all($pattern, $config, $matches);

        // Iterate through the ending numbers and select the version string from the line with the highest ending number
        foreach ($matches[1] as $index => $versionString) {
            $endingNumber = (int)$matches[2][$index];
            
            if ($endingNumber > $highestEndingNumber) {
                $highestEndingNumber = $endingNumber;
                $selectedVersionString = $versionString;
            }
        }

        if (!empty($selectedVersionString)) {
            $item->anyconnectMac_ver = $selectedVersionString;
        } else {
            $item->anyconnectMac_ver = null;
        }

        $lines = explode("\n", $config);

        foreach ($lines as $line) {
            if (trim($line) === 'failover') {
                $item->clustered = TRUE;
                break;
            }
        }

        if(!$item->clustered){
            $item->clustered = FALSE;
        }
		return $item;
	});

        return [
		"devices" => $devices
        ];
    }

    final public function handle(string $pluginName): array
    {
        return array_merge([
            'settings_view' => Str::start($this->view, "$pluginName::"),
        ], $this->data());
    }
}

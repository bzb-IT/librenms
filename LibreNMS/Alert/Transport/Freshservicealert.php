<?php

namespace LibreNMS\Alert\Transport;

use LibreNMS\Alert\Transport;
use GuzzleHttp\Client;
use App\Models\Device;

class Freshservicealert extends Transport
{
    public function deliverAlert(array $alert_data): bool
    {
        $url = $this->config['api-url'];
        $key = $this->config['api-key'];

        return $this->contactFreshservice($alert_data, $url, $key);
    }

    private function contactFreshservice($obj, $url, $key)
    {

        $fs_alarm_obj = [];

        $device = Device::find($obj['device_id']);
        $groupName = $device->groups()->select('name')->where('name', 'like', 'ORG:%')->first()['name'];
        $fs_alarm_obj['org_name'] = \Str::after($groupName,'ORG:');

        $fs_alarm_obj['resource'] = $obj['hostname'];
        $fs_alarm_obj['metric_name'] = $obj['hostname'] . " | " . $obj['name'];
        $fs_alarm_obj['metric_value'] = $obj['state'];
        $fs_alarm_obj['node'] = $fs_alarm_obj['resource'];
        $fs_alarm_obj['message'] = $fs_alarm_obj['metric_name'];
        $fs_alarm_obj['severity'] = $obj['severity'];
        $fs_alarm_obj['time'] = $obj['timestamp'];

        $client = new \GuzzleHttp\Client();

        $options = [
          'body' => json_encode($fs_alarm_obj),
          'headers' => ['Authorization' => $key],
        ];

        $res = $client->request('POST', $url, $options);
        $code = $res->getStatusCode();

        if (!in_array($code, array('200','202'))) {
            var_dump("API '$host' returned Error");
            var_dump('Params:');
            var_dump($query);
            var_dump('Response headers:');
            var_dump($res->getHeaders());
            var_dump('Return: ' . $res->getReasonPhrase());

            return 'HTTP Status code ' . $code;
        }

        return true;
    }

    public static function configTemplate(): array
    {
        return [
            'config' => [
                [
                    'title' => 'API URL',
                    'name' => 'api-url',
                    'descr' => 'API URL',
                    'type' => 'text',
                ],
                [
                    'title' => 'API KEY',
                    'name' => 'api-key',
                    'descr' => 'API KEY',
                    'type' => 'text',
                ]
            ],
            'validation' => [
                'api-url' => 'required|url',
                'api-key' => 'required',
            		'api-headers',
            		'api-body',
            ]
        ];

    }
}

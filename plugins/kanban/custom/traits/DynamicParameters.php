<?php namespace Kanban\Custom\Traits;

use Response;

trait DynamicParameters
{
    public function getExpectedParameters()
    {
        if(!property_exists($this, 'parameters')) {
            throw new \Exception('Dynamic parameters must be defined in $parameters property.');
        }

        return $this->parseExpectedParameters($this->parameters);
    }

    public function mapActualParameters($actual)
    {
        $expected = $this->getExpectedParameters();
        $formatted = []; $index = 0;

        if(count($actual) < count($expected['required'])) {
            return [];
        }

        // map required parameters
        foreach($expected['required'] as $required) {
            $formatted[$required] = $actual[$index++];
        }

        // map optional parameters
        foreach($expected['optional'] as $optional) {
            if(isset($actual[$index])) {
                $formatted[$optional] = $actual[$index++];
            }
        }

        return $formatted;
    }

    public function dynamicParam($name, $default = null)
    {
        if($override = $this->property($name)) {
            return $override;
        }

        return $this->param($name, $default);
    }

    protected function parseExpectedParameters($parameters)
    {
        $parsed = ['required' => [], 'optional' => []];

        foreach($parameters as $parameter) {
            if(ends_with($parameter, '?')) {
                $parsed['optional'][] = substr($parameter, 0, -1);
            } else {
                $parsed['required'][] = $parameter;
            }
        }

        return $parsed;
    }

    protected function withDynamicParameters(array $properties)
    {
        $parameters = $this->getExpectedParameters();

        foreach($parameters['required'] as $parameter) {
            $properties[$parameter] = array_merge(
                $this->createProperty($parameter), ['required' => true]
            );

        }

        foreach($parameters['optional'] as $parameter) {
            $properties[$parameter] = $this->createProperty($parameter);
        }

        return $properties;
    }

    protected function createProperty($parameter)
    {
        return [
            'title'       => 'URL parameter for :' . $parameter,
            'description' => 'Enter the name of the :' . $parameter . ' URL parameter',
            'default'     => '{{ :' . $parameter . ' }}',
            'type'        => 'string',
            'advanced'    => true
        ];
    }
}
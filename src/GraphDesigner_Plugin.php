<?php
namespace Plugins;

use Exception;
use ReflectionClass;
use ReflectionMethod;

class GraphDesigner_Plugin {
    public function __call($name, $arguments)
    {
        if  (method_exists($this, $name)) {
            call_user_func_array([$this, $name], $arguments);
        }
    }
    
    public function getActions()
    {
        $reflectionClass = new ReflectionClass($this);

        $pluginActions = array();
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PRIVATE | ReflectionMethod::IS_PROTECTED) as $method) {
            if (!($method->class instanceof self) && preg_match('/Action$/', $method->name)) {
                $reflectionMethod = new ReflectionMethod($method->class, $method->name);

                $paramsDocBlockData = $this->readParamsDocBlock($reflectionMethod);

                $parameters = array();
                foreach ($reflectionMethod->getParameters() as $parameter) {
                    $name = $parameter->getName();

                    $isOptional = $parameter->isOptional();

                    $paramData = array(
                        'name' => $name,
                        'optional' => array(
                            $isOptional,
                            $isOptional ? $parameter->getDefaultValue() : null
                        ),
                    );

                    if (isset($paramsDocBlockData[$name])) {
                        $paramData['types'] = $paramsDocBlockData[$name]['types'];
                        $paramData['description'] = $paramsDocBlockData[$name]['description'];
                    }

                    $parameters[] = $paramData;
                }

                $pluginActions[] = array('name' => $method->name, 'parameters' => $parameters, 'docBlock' => $this->readMethodDocBlock($reflectionMethod));
            }
        }

        return $pluginActions;
    }

    private function checkReflectionObject($reflectionObject)
    {
        if (($reflectionObject instanceof ReflectionClass) || ($reflectionObject instanceof ReflectionMethod)) {
            return true;
        } else {
            throw new Exception('Not reflection class sent');
        }
    }

    private function readMethodDocBlock($reflectionObject)
    {
        $this->checkReflectionObject($reflectionObject);

        $docBlock = $reflectionObject->getDocComment();

        if (preg_match('/\@name[ ]*((?:[^\/\*]+[\/]?[\r\n]{1,2})+)/', $docBlock, $matches)) {
            $name = trim($matches[1]);
        } else {
            $name = $reflectionObject->getName();
        }

            
        if (preg_match('/\@description[ ]*((?:[^\/\*]+[\/]?[\r\n]{1,2})+)/', $docBlock, $matches)) {
            $description = preg_replace('/[ ]{2,}/', ' ', preg_replace('/[\/]?[\r\n]/', null, $matches[1]));
        } else {
            $description = null;
        }

        return array('name' => $name, 'description' => $description);
    }

    public function readParamsDocBlock($reflectionObject)
    {
        $this->checkReflectionObject($reflectionObject);

        preg_match_all('/\@param ([^ \$]*)[ ]?\$([\w\_]+)(?:[ ]*((?:[^\/\*]+[\/]?[\r\n]{1,2})+))/', $reflectionObject->getDocComment(), $matches);

        $params = array();

        foreach ($matches[3] as $index => $match) {
            $params[$matches[2][$index]] = array(
                'types' => explode('|', $matches[1][$index]),
                'description' => preg_replace('/[ ]{2,}/', ' ', preg_replace('/[\/]?[\r\n]/', null, $match)),
            );
        }

        return $params;
    }
}

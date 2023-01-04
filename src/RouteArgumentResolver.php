<?php declare(strict_types=1);

namespace Pollen\Routing;

use Pollen\ArgumentResolver\AbstractResolver;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionParameter;

class RouteArgumentResolver extends AbstractResolver
{
    protected array $params;

    public function __construct(Route $route)
    {
        $this->params = $route->getVars();
    }

    /**
     * @inheritDoc
     */
    public function resolve(ReflectionParameter $parameter): ?array
    {
        $key = $parameter->getName();
        if (!isset($this->params[$key])) {
            return null;
        }

        $value = $this->matchTypedValue($parameter, $this->params[$key]);
        if ($value === null) {
            return null;
        }

        return [$key, $value];
    }

    /**
     * @return array|string|int
     */
    protected function matchTypedValue(ReflectionParameter $parameter, $value)
    {
        if (!$type = $parameter->getType()) {
            return $value;
        }

        $typeName = $type->getName();

        switch ($typeName) {
            case 'array':
                if (is_array($value)) {
                    return $value;
                }
                break;
            case 'string':
                if (is_string($value)) {
                    return $value;
                }
                break;
            case 'bool':
                if (is_bool($value)) {
                    return $value;
                }
                switch (strtolower($value)) {
                    case '1':
                    case '0':
                    case 'true':
                    case 'false':
                    case 'on':
                    case 'off':
                    case 'yes':
                    case 'no' :
                        return filter_var($value, FILTER_VALIDATE_BOOL);
                }
                break;
            case 'float':
                if (is_float($value)) {
                    return $value;
                }

                $float_value = (float) $value;
                if (($value === (string) $float_value) || (is_numeric($value) && strpos($value, '.'))) {
                    return $float_value;
                }
                break;
            case 'int':
                if (is_numeric($value)) {
                    return (int)$value;
                }
                break;
        }

        return null;
    }
}

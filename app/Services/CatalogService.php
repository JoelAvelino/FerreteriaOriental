<?php

namespace App\Services;

use App\Repositories\CatalogRepository;
use PDOException;
use RuntimeException;

class CatalogService
{
    private const MODULES = [
        'categorias' => [
            'title' => 'Categorías',
            'table' => 'categoria',
            'fields' => [
                'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 150],
                'descripcion' => ['label' => 'Descripción', 'required' => false, 'type' => 'textarea'],
            ],
        ],
        'marcas' => [
            'title' => 'Marcas',
            'table' => 'marca',
            'fields' => [
                'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 150],
                'pais_origen' => ['label' => 'País de origen', 'required' => true, 'max' => 120],
            ],
        ],
        'proveedores' => [
            'title' => 'Proveedores',
            'table' => 'proveedor',
            'fields' => [
                'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 150],
                'contacto' => ['label' => 'Persona de contacto', 'required' => true, 'max' => 150],
                'telefono' => ['label' => 'Teléfono', 'required' => false, 'max' => 30],
                'correo' => ['label' => 'Correo electrónico', 'required' => false, 'max' => 150, 'type' => 'email'],
            ],
        ],
        'clientes' => [
            'title' => 'Clientes',
            'table' => 'cliente',
            'success_message' => 'Cliente registrado correctamente.',
            'fields' => [
                'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 150],
                'telefono' => ['label' => 'Teléfono', 'required' => false, 'max' => 30],
                'correo' => ['label' => 'Correo electrónico', 'required' => false, 'max' => 150, 'type' => 'email'],
                'direccion' => ['label' => 'Dirección', 'required' => false, 'type' => 'textarea'],
            ],
        ],
        'metodos_pago' => [
            'title' => 'Métodos de pago',
            'table' => 'metodo_pago',
            'success_message' => 'Método de pago registrado correctamente.',
            'fields' => [
                'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 150],
                'descripcion' => ['label' => 'Descripción', 'required' => false, 'type' => 'textarea'],
            ],
        ],
        'ventas' => [
            'title' => 'Ventas',
            'table' => 'venta',
            'fields' => [],
            'show_listing' => false,
        ],
        'productos' => [
            'title' => 'Productos',
            'form_title' => 'Registrar producto',
            'table' => 'producto',
            'show_listing' => false,
            'success_message' => 'Producto registrado correctamente.',
            'fields' => [
                'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 150],
                'descripcion' => ['label' => 'Descripción', 'required' => false, 'type' => 'textarea'],
                'precio_compra' => ['label' => 'Precio de compra', 'required' => false, 'type' => 'decimal'],
                'precio_venta' => ['label' => 'Precio de venta', 'required' => false, 'type' => 'decimal'],
                'stock' => ['label' => 'Stock actual', 'required' => false, 'type' => 'integer'],
                'id_categoria' => ['label' => 'Categoría', 'required' => true, 'type' => 'select'],
                'id_marca' => ['label' => 'Marca', 'required' => true, 'type' => 'select'],
                'id_proveedor' => ['label' => 'Proveedor', 'required' => true, 'type' => 'select'],
            ],
        ],
    ];

    private CatalogRepository $repository;

    public function __construct(CatalogRepository $repository)
    {
        $this->repository = $repository;
    }

    public function modules(): array
    {
        return self::MODULES;
    }

    public function getModule(string $module): ?array
    {
        return self::MODULES[$module] ?? null;
    }

    public function list(string $module): array
    {
        $definition = $this->requireModule($module);

        return $this->repository->list(
            $definition['table'],
            array_keys($definition['fields'])
        );
    }

    public function options(string $module): array
    {
        $definition = $this->requireModule($module);
        $options = [];

        foreach ($definition['fields'] as $name => $field) {
            if (($field['type'] ?? '') === 'select') {
                $options[$name] = $this->repository->referenceOptions($name);
            }
        }

        return $options;
    }

    public function validateProductFilters(array $input): array
    {
        $filters = [
            'busqueda' => '',
            'id_categoria' => null,
            'id_marca' => null,
            'id_proveedor' => null,
        ];
        $errors = [];

        $search = $input['busqueda'] ?? '';
        if (!is_string($search)) {
            $errors['busqueda'] = 'El texto de búsqueda no tiene un formato válido.';
        } else {
            $filters['busqueda'] = trim($search);
        }

        foreach (['id_categoria', 'id_marca', 'id_proveedor'] as $field) {
            $rawValue = $input[$field] ?? '';
            if (!is_string($rawValue) && !is_numeric($rawValue)) {
                $errors[$field] = 'El filtro seleccionado no tiene un formato válido.';
                continue;
            }

            $value = trim((string) $rawValue);
            if ($value === '') {
                continue;
            }

            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                $errors[$field] = 'Selecciona un filtro válido.';
                continue;
            }
            if (!$this->repository->referenceExists($field, $id)) {
                $errors[$field] = 'La opción seleccionada ya no existe. Recarga el catálogo.';
                continue;
            }

            $filters[$field] = $id;
        }

        return [$filters, $errors];
    }

    public function searchProducts(array $filters): array
    {
        $this->requireModule('productos');

        return $this->repository->searchProducts($filters);
    }

    public function findProduct(int $id): ?array
    {
        $this->requireModule('productos');
        if ($id < 1) {
            return null;
        }

        return $this->repository->findProduct($id);
    }

    public function validate(string $module, array $input): array
    {
        $definition = $this->requireModule($module);
        $values = [];
        $errors = [];

        foreach ($definition['fields'] as $name => $field) {
            $rawValue = $input[$name] ?? '';
            if (!is_string($rawValue) && !is_numeric($rawValue)) {
                $errors[$name] = 'El formato de ' . $field['label'] . ' no es válido.';
                continue;
            }
            $value = trim((string) $rawValue);

            if ($value === '') {
                if ($field['required']) {
                    $errors[$name] = $field['label'] . ' es obligatorio.';
                } elseif (($field['type'] ?? '') === 'integer') {
                    continue;
                } else {
                    $values[$name] = null;
                }
                continue;
            }

            if (isset($field['max']) && $this->length($value) > $field['max']) {
                $errors[$name] = sprintf(
                    '%s no puede exceder %d caracteres.',
                    $field['label'],
                    $field['max']
                );
                continue;
            }

            if (($field['type'] ?? '') === 'select') {
                $id = filter_var($value, FILTER_VALIDATE_INT);
                if ($id === false || $id < 1) {
                    $errors[$name] = 'Selecciona una opción válida para ' . $field['label'] . '.';
                    continue;
                }
                if (!$this->repository->referenceExists($name, $id)) {
                    $errors[$name] = 'La opción seleccionada para ' . $field['label'] . ' ya no existe. Recarga la página.';
                    continue;
                }

                $values[$name] = $id;
                continue;
            }

            if (($field['type'] ?? '') === 'integer') {
                $integer = filter_var($value, FILTER_VALIDATE_INT);
                if ($integer === false) {
                    $errors[$name] = $field['label'] . ' debe ser un número entero válido.';
                    continue;
                }
                if ($integer < -2147483648 || $integer > 2147483647) {
                    $errors[$name] = $field['label'] . ' excede el rango permitido por la base de datos.';
                    continue;
                }
                if ($value !== '') {
                    $values[$name] = $integer;
                }
                continue;
            }

            if (($field['type'] ?? '') === 'decimal') {
                if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $value)) {
                    $errors[$name] = $field['label'] . ' debe usar un número con hasta dos decimales.';
                    continue;
                }

                $integerPart = ltrim(explode('.', ltrim($value, '-'))[0], '0');
                if (strlen($integerPart) > 8) {
                    $errors[$name] = $field['label'] . ' excede la capacidad permitida por la base de datos.';
                    continue;
                }

                $values[$name] = $value;
                continue;
            }

            if (($field['type'] ?? '') === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $errors[$name] = 'Ingresa un correo electrónico válido.';
                continue;
            }

            if ($name === 'telefono') {
                if ($module === 'clientes') {
                    if (!preg_match('/^\+?[0-9(). -]{7,30}$/D', $value)) {
                        $errors[$name] = 'Ingresa un teléfono válido (de 7 a 30 caracteres y con + solo al inicio).';
                        continue;
                    }

                    $digits = preg_replace('/\D/', '', $value);
                    $normalizedPhone = (str_starts_with($value, '+') ? '+' : '') . $digits;
                    if (strlen($digits) < 7 || strlen($normalizedPhone) > 30) {
                        $errors[$name] = 'El teléfono debe contener entre 7 y 30 dígitos y caber en el campo.';
                        continue;
                    }

                    $values[$name] = $normalizedPhone;
                    continue;
                }

                if (!preg_match('/^[0-9+(). -]{7,30}$/', $value)) {
                    $errors[$name] = 'Ingresa un teléfono válido (de 7 a 30 caracteres).';
                    continue;
                }
            }

            $values[$name] = $value;
        }

        if ($module === 'clientes') {
            if (!isset($errors['telefono']) && $values['telefono'] !== null && $this->repository->clientPhoneExists($values['telefono'])) {
                $errors['telefono'] = 'Este teléfono ya está registrado.';
            }
            if (!isset($errors['correo']) && $values['correo'] !== null && $this->repository->clientEmailExists($values['correo'])) {
                $errors['correo'] = 'Este correo electrónico ya está registrado.';
            }
        }

        if ($module === 'metodos_pago' && !isset($errors['nombre']) && $this->repository->paymentMethodNameExists($values['nombre'])) {
            $errors['nombre'] = 'Ya existe un método de pago con ese nombre.';
        }

        return [$values, $errors];
    }

    public function create(string $module, array $values): void
    {
        $definition = $this->requireModule($module);
        $this->repository->create($definition['table'], $values);
    }

    public function updateProduct(int $id, array $values): bool
    {
        $this->requireModule('productos');
        $values['stock'] = $values['stock'] ?? 0;

        return $this->repository->updateProduct($id, $values);
    }

    public function delete(string $module, int $id): bool
    {
        $tables = [
            'categorias' => 'categoria',
            'proveedores' => 'proveedor',
            'productos' => 'producto',
        ];
        if (!isset($tables[$module]) || $id < 1) {
            throw new RuntimeException('El registro solicitado no se puede eliminar.');
        }

        try {
            return $this->repository->delete($tables[$module], $id);
        } catch (PDOException $exception) {
            $errorInfo = $exception->errorInfo;
            if ($exception->getCode() === '23000' && isset($errorInfo[1]) && (int) $errorInfo[1] === 1451) {
                $messages = [
                    'categorias' => 'No se puede eliminar la categoría porque tiene productos asociados.',
                    'proveedores' => 'No se puede eliminar el proveedor porque tiene productos asociados.',
                    'productos' => 'No se puede eliminar el producto porque tiene ventas asociadas.',
                ];
                throw new RuntimeException($messages[$module], 0, $exception);
            }

            throw $exception;
        }
    }

    private function requireModule(string $module): array
    {
        if (!isset(self::MODULES[$module])) {
            throw new \InvalidArgumentException('El módulo solicitado no existe.');
        }

        return self::MODULES[$module];
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

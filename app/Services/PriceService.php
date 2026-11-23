<?php

namespace App\Services;

use App\Repositories\CatalogRepository;
use RuntimeException;

class PriceValidationException extends RuntimeException
{
}

class PriceProductNotFoundException extends RuntimeException
{
}

class PriceService
{
    private const MAX_WHOLE_DIGITS = 8;

    private CatalogRepository $repository;

    public function __construct(CatalogRepository $repository)
    {
        $this->repository = $repository;
    }

    public function searchProducts(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        if (strlen($term) > 150) {
            throw new PriceValidationException('La búsqueda no puede exceder 150 caracteres.');
        }

        if (preg_match('/^\d+$/D', $term)) {
            $id = filter_var($term, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1 || $id > 4294967295) {
                return [];
            }

            return $this->repository->findProductsForPriceById($id);
        }

        $length = preg_match_all('/./us', $term, $matches);
        if ($length === false || $length < 2) {
            throw new PriceValidationException('Escribe al menos dos caracteres para buscar por nombre.');
        }

        return $this->repository->searchProductsForPriceByName($term);
    }

    public function validateNewPrice($input): string
    {
        if (!is_string($input)) {
            throw new PriceValidationException('El nuevo precio debe ser un valor numérico válido.');
        }

        $price = trim($input);
        if ($price === '') {
            throw new PriceValidationException('El nuevo precio es obligatorio.');
        }
        if (strlen($price) > 64 || !preg_match('/^\d+(?:\.\d{1,2})?$/D', $price)) {
            throw new PriceValidationException('El nuevo precio debe ser numérico y tener como máximo dos decimales.');
        }

        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > self::MAX_WHOLE_DIGITS) {
            throw new PriceValidationException('El nuevo precio excede el máximo permitido de 99,999,999.99.');
        }

        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        if ($cents <= 0) {
            throw new PriceValidationException('El nuevo precio debe ser mayor que cero.');
        }

        return $whole . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function updatePrice(int $productId, $input): void
    {
        if ($productId < 1) {
            throw new PriceValidationException('Selecciona un producto válido.');
        }

        $price = $this->validateNewPrice($input);
        $result = $this->repository->updateProductPrice($productId, $price);
        if (!$result['exists']) {
            throw new PriceProductNotFoundException('El producto seleccionado ya no existe. Vuelve a buscarlo.');
        }
        if (!$result['price_changed']) {
            throw new PriceValidationException('El nuevo precio debe ser diferente al precio actual.');
        }
    }
}

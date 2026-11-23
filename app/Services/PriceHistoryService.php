<?php

namespace App\Services;

use App\Repositories\PriceHistoryRepository;

class PriceHistoryService
{
    private PriceHistoryRepository $repository;

    public function __construct(PriceHistoryRepository $repository)
    {
        $this->repository = $repository;
    }

    public function validateFilters(array $input): array
    {
        $filters = [
            'producto' => '',
            'fecha_inicio' => '',
            'fecha_fin' => '',
        ];
        $errors = [];

        foreach (array_keys($filters) as $field) {
            $value = $input[$field] ?? '';
            if (!is_string($value)) {
                $errors[$field] = 'El filtro no tiene un formato válido.';
                continue;
            }

            $filters[$field] = trim($value);
        }

        if (!isset($errors['producto'])) {
            $length = preg_match_all('/./us', $filters['producto'], $matches);
            if ($length === false || $length > 150) {
                $errors['producto'] = 'El filtro de producto no puede exceder 150 caracteres.';
            }
        }

        foreach (['fecha_inicio' => 'inicial', 'fecha_fin' => 'final'] as $field => $label) {
            if (
                $filters[$field] !== ''
                && !$this->isValidDate($filters[$field])
            ) {
                $errors[$field] = 'La fecha ' . $label . ' no es válida.';
            }
        }

        if (
            !isset($errors['fecha_inicio'])
            && !isset($errors['fecha_fin'])
            && $filters['fecha_inicio'] !== ''
            && $filters['fecha_fin'] !== ''
            && $filters['fecha_inicio'] > $filters['fecha_fin']
        ) {
            $errors['fecha_fin'] = 'La fecha final no puede ser anterior a la fecha inicial.';
        }

        return [$filters, $errors];
    }

    public function search(array $filters): array
    {
        return $this->repository->search($filters);
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }
}

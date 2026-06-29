<?php

namespace App\Services\Daftra\Query;

class DaftraQuery
{
    private array $filters = [];
    private array $params = [];
    private int $limit = 20;
    private int $page = 1;
    private ?string $sortField = null;
    private ?string $sortDirection = null;

    public function where(string $field, mixed $value): static
    {
        $this->filters[] = new DaftraFilter($field, $value);
        return $this;
    }

    public function whereClient(int|string $client): static
    {
        $field = is_int($client) ? 'client_id' : 'client_business_name';
        return $this->where($field, $client);
    }

    public function whereProduct(int|string $product): static
    {
        $field = is_int($product) ? 'id' : 'item';
        return $this->where($field, $product);
    }

    public function whereSupplier(int|string $supplier): static
    {
        $field = is_int($supplier) ? 'supplier_id' : 'business_name';
        return $this->where($field, $supplier);
    }

    public function whereStore(int $storeId): static
    {
        return $this->where('store_id', $storeId);
    }

    public function whereStaff(int $staffId): static
    {
        return $this->where('staff_id', $staffId);
    }

    public function whereStatus(string $status): static
    {
        return $this->where('status', $status);
    }

    public function whereDateBetween(string $from, string $to): static
    {
        $this->params['date_from'] = $from;
        $this->params['date_to'] = $to;
        return $this;
    }

    public function whereDateFrom(string $date): static
    {
        $this->params['date_from'] = $date;
        return $this;
    }

    public function whereDateTo(string $date): static
    {
        $this->params['date_to'] = $date;
        return $this;
    }

    public function search(string $term): static
    {
        $this->params['search'] = $term;
        return $this;
    }

    public function paginate(int $limit = 20, int $page = 1): static
    {
        $this->limit = min(max($limit, 1), 1000);
        $this->page = max($page, 1);
        return $this;
    }

    public function sortBy(string $field, string $direction = 'asc'): static
    {
        $this->sortField = $field;
        $this->sortDirection = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        return $this;
    }

    public function toQueryParams(): array
    {
        $params = $this->params;

        foreach ($this->filters as $filter) {
            $params[$filter->field] = $filter->value;
        }

        $params['limit'] = $this->limit;
        $params['page'] = $this->page;

        if ($this->sortField) {
            $params['sort'] = $this->sortField;
            $params['sort_direction'] = $this->sortDirection;
        }

        return $params;
    }
}

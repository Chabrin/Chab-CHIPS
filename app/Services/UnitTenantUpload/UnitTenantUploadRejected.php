<?php

namespace App\Services\UnitTenantUpload;

use DomainException;

/**
 * Thrown when an uploaded file has validation errors; nothing is staged.
 */
class UnitTenantUploadRejected extends DomainException
{
    private array $errors;

    /**
     * @param array $errors list of ['sheet' => ?string, 'row' => ?int, 'unit_number' => ?string, 'message' => string]
     */
    public function __construct(array $errors)
    {
        parent::__construct(count($errors) . ' error(s) found. Fix them and upload again; nothing was saved.');
        $this->errors = $errors;
    }

    /**
     * The per-row errors.
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

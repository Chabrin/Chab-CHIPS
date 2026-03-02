<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait CompanyAccessChecker
{
    /**
     * Get the list of company IDs that the authenticated user has access to.
     *
     * @return array
     */
    protected function getUserCompanyIds(): array
    {
        $user = Auth::user();
        $companyIds = [];

        if ($user && isset($user->role_companies) && is_array($user->role_companies)) {
            $companyIds = $user->role_companies;
        }

        return $companyIds;
    }

    /**
     * Check if the user has access to a specific company.
     *
     * @param int $companyId
     * @return bool
     */
    protected function userHasAccessToCompany(int $companyId): bool
    {
        $companyIds = $this->getUserCompanyIds();

        // If user has no company restrictions, allow access to all
        if (empty($companyIds)) {
            return true;
        }

        return in_array($companyId, $companyIds);
    }

    /**
     * Apply company access filter to a query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $columnName
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyCompanyAccessFilter($query, string $columnName = 'company_id')
    {
        $companyIds = $this->getUserCompanyIds();

        if (!empty($companyIds)) {
            $query->whereIn($columnName, $companyIds);
        }

        return $query;
    }

    /**
     * Check if user has company access, return empty response if not.
     *
     * @return array|null Returns null if user has access, or an empty response array if not
     */
    protected function checkCompanyAccess(): ?array
    {
        $companyIds = $this->getUserCompanyIds();

        // If user has no assigned companies, return empty response
        if (empty($companyIds)) {
            return ['success' => true, 'data' => []];
        }

        return null;
    }
}

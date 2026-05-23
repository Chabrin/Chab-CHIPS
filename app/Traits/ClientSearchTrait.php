<?php

namespace App\Traits;

use App\Models\Client;
use App\Services\ErrorDispatcher;
use Illuminate\Http\Request;

trait ClientSearchTrait
{
    /**
     * Select2-compatible client search.
     * Expects ?q=... and returns { results: [{ id, text }] }.
     */
    public function searchClientsAjax(Request $request)
    {
        try {
            $term = trim($request->get('q', ''));
            if (strlen($term) < 2) {
                return response()->json(['results' => []]);
            }

            $lower = strtolower($term);

            $clients = Client::clients()
                ->select('id', 'names', 'reference_number')
                ->where(function ($q) use ($lower) {
                    $q->whereRaw('LOWER(names) LIKE ?', ["%{$lower}%"])
                        ->orWhereRaw('LOWER(reference_number) LIKE ?', ["%{$lower}%"]);
                })
                ->orderBy('names')
                ->limit(20)
                ->get()
                ->map(fn($c) => [
                    'id'   => $c->id,
                    'text' => $c->names . ($c->reference_number ? ' (' . $c->reference_number . ')' : ''),
                ]);

            return response()->json(['results' => $clients]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            return response()->json(['results' => []]);
        }
    }
}

<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Creative;
use App\Models\ParameterCategory;
use App\Models\ParameterValue;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Generates the human readable creative reference, e.g. PAC-W-60-69-HIGHBILL-AID-FB-001.
 *
 * Which parameter categories take part is admin-controlled (`in_naming`), so the
 * convention can evolve without a code change.
 */
class CreativeNaming
{
    /**
     * @param  array<int, int>  $parameterValueIds
     * @param  array<int, int>  $channelIds
     */
    public function suggest(?int $productId, array $parameterValueIds, array $channelIds): string
    {
        $parts = [];

        if ($productId && $product = Product::find($productId)) {
            $parts[] = $product->code;
        }

        $namingCategories = ParameterCategory::query()
            ->where('in_naming', true)
            ->orderBy('position')
            ->pluck('id')
            ->all();

        if ($parameterValueIds !== []) {
            $values = ParameterValue::query()
                ->whereIn('id', $parameterValueIds)
                ->whereIn('parameter_category_id', $namingCategories)
                ->get()
                ->sortBy(fn ($v) => array_search($v->parameter_category_id, $namingCategories, true));

            foreach ($values as $value) {
                $parts[] = $value->code;
            }
        }

        if ($channelIds !== []) {
            $channel = Channel::whereIn('id', $channelIds)->orderBy('position')->first();

            if ($channel) {
                $parts[] = $channel->code;
            }
        }

        $prefix = $this->normalise(implode('-', array_filter($parts))) ?: 'CREA';

        return $prefix.'-'.$this->nextSequence($prefix);
    }

    /**
     * Ensures the reference is unique, appending/incrementing the numeric suffix.
     */
    public function ensureUnique(string $reference, ?int $ignoreCreativeId = null): string
    {
        $reference = $this->normalise($reference) ?: 'CREA-001';

        $exists = fn (string $ref) => Creative::query()
            ->where('reference', $ref)
            ->when($ignoreCreativeId, fn ($q) => $q->whereKeyNot($ignoreCreativeId))
            ->exists();

        if (! $exists($reference)) {
            return $reference;
        }

        $prefix = preg_replace('/-\d+$/', '', $reference);
        $candidate = $prefix.'-'.$this->nextSequence($prefix, $ignoreCreativeId);

        $guard = 0;
        while ($exists($candidate) && $guard++ < 500) {
            $candidate = $prefix.'-'.str_pad((string) ((int) Str::afterLast($candidate, '-') + 1), 3, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }

    private function nextSequence(string $prefix, ?int $ignoreCreativeId = null): string
    {
        $last = Creative::query()
            ->where('reference', 'like', $prefix.'-%')
            ->when($ignoreCreativeId, fn ($q) => $q->whereKeyNot($ignoreCreativeId))
            ->pluck('reference')
            ->map(fn ($ref) => (int) Str::afterLast($ref, '-'))
            ->max() ?? 0;

        return str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }

    private function normalise(string $value): string
    {
        $value = Str::of($value)->ascii()->upper()->replaceMatches('/[^A-Z0-9\-]+/', '-')->replaceMatches('/-+/', '-')->trim('-');

        return (string) $value;
    }
}

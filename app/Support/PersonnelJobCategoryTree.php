<?php

declare(strict_types=1);

namespace App\Support;

/** Present the existing category hierarchy without queries or a selector per row. */
final class PersonnelJobCategoryTree
{
    public static function rows(array $categories): array
    {
        $byId = $children = [];
        foreach ($categories as $category) {
            $byId[(int) $category['id']] = $category;
        }
        foreach ($byId as $id => $category) {
            $parent = (int) ($category['parent_id'] ?? 0);
            $parent = $parent !== $id && isset($byId[$parent]) ? $parent : 0;
            $children[$parent][] = $id;
        }
        $rows = $visited = [];
        // The second pass retains old orphaned/cyclic records without an infinite traversal.
        $starts = array_merge($children[0] ?? [], array_keys($byId));
        foreach ($starts as $start) {
            if (isset($visited[$start])) continue;
            $stack = [[$start, 0, '', 0]];
            while ($stack !== []) {
                [$id, $depth, $prefix, $displayParent] = array_pop($stack);
                if (isset($visited[$id])) continue;
                $visited[$id] = true;
                $category = $byId[$id];
                $path = ($prefix !== '' ? $prefix . ' / ' : '') . $category['name'];
                $rows[] = $category;
                $index = array_key_last($rows);
                $rows[$index]['depth'] = $depth;
                $rows[$index]['path'] = $path;
                $rows[$index]['display_parent_id'] = $displayParent;
                $rows[$index]['parent_name'] = $byId[$displayParent]['name'] ?? '';
                $rows[$index]['sub_category_count'] = 0;
                $rows[$index]['branch_job_count'] = (int) ($category['job_count'] ?? 0);
                foreach (array_reverse($children[$id] ?? []) as $child) {
                    $stack[] = [$child, $depth + 1, $path, $id];
                }
            }
        }
        $indexes = array_flip(array_column($rows, 'id'));
        for ($i = count($rows) - 1; $i >= 0; $i--) {
            $parent = $rows[$i]['display_parent_id'];
            if (isset($indexes[$parent])) {
                $p = $indexes[$parent];
                $rows[$p]['sub_category_count'] += 1 + $rows[$i]['sub_category_count'];
                $rows[$p]['branch_job_count'] += $rows[$i]['branch_job_count'];
            }
        }
        return $rows;
    }

    /** Include the selected category, so it and its descendants cannot become its parent. */
    public static function descendantIds(array $categories, int $id): array
    {
        $children = [];
        foreach ($categories as $category) $children[(int) ($category['parent_id'] ?? 0)][] = (int) $category['id'];
        $seen = [];
        $stack = [$id];
        while ($stack !== []) {
            $current = array_pop($stack);
            if (isset($seen[$current])) continue;
            $seen[$current] = true;
            foreach ($children[$current] ?? [] as $child) $stack[] = $child;
        }
        return array_keys($seen);
    }
}

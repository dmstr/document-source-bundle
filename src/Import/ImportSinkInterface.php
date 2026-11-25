<?php

// file generated with AI assistance: Claude Code - 2026-10-08 12:04:33 UTC

declare(strict_types=1);

namespace Dmstr\DocumentSource\Import;

/**
 * Port of a document-source sync into the application's document store.
 * The bundle lists, selects and reads files; the application implements
 * this interface and turns one {@see SourceItem} into one document of the
 * collection behind the scope.
 *
 * A scope is an opaque string the application chooses (the UUID of a sync
 * definition): every document written for it is attributed to it, and
 * {@see deleteVanished()} only ever touches documents of the same scope, so
 * two scopes on the same connection never remove each other's documents.
 *
 * The bundle never instantiates an implementation: the application aliases
 * this interface to its service.
 */
interface ImportSinkInterface
{
    /**
     * Imports one item to completion: create the document or replace the
     * one with the same source key in this scope, or skip it when the content
     * is unchanged and already embedded.
     *
     * Returns a FAILED outcome for item-level problems (content hash held by
     * another document of the collection, unsupported or broken file,
     * extraction or embedding failure) and never throws for them; throws
     * only when the run cannot continue (entity manager closed, storage
     * unavailable, unknown scope).
     *
     * @param string                              $scope    opaque scope key, e.g. the sync definition UUID
     * @param (callable(int, int): void)|null     $progress `callable(int $done, int $total): void`, progress within the item (e.g. embedding)
     */
    public function import(string $scope, SourceItem $item, ?callable $progress = null): ItemOutcome;

    /**
     * Removes the documents of $scope whose source key is not in $seenKeys.
     * With $runClean false nothing is removed and 0 is returned: a run is
     * clean only when the listing was complete and no item failed (an item
     * that failed to read or import still counts as seen).
     *
     * @param list<string> $seenKeys source keys of every file the run listed and selected
     *
     * @return int number of documents removed
     */
    public function deleteVanished(string $scope, array $seenKeys, bool $runClean): int;
}

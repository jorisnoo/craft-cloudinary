<?php

namespace Noo\CraftCloudinary\console\controllers;

use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\db\Table;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use yii\console\ExitCode;
use yii\db\JsonExpression;

/**
 * Recover from a past reconciler mass-deletion by remapping relations (and
 * optionally CKEditor-style {asset:ID} reference tags) from soft-deleted
 * asset IDs to their re-created live counterparts.
 *
 * Matches soft-deleted and live assets on (volumeId, folderId, filename).
 * Only works within Craft's soft-delete retention window before GC removes
 * the deleted rows.
 */
class RecoverController extends Controller
{
    public bool $dryRun = false;

    public bool $refTags = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun', 'refTags']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['d' => 'dryRun', 'r' => 'refTags']);
    }

    public function actionRelations(): int
    {
        $mapping = $this->buildAssetIdMapping();

        if (empty($mapping)) {
            $this->stdout("No deleted→live asset mappings found for Cloudinary volumes.\n");
            return ExitCode::OK;
        }

        $this->stdout(sprintf("Found %d soft-deleted Cloudinary asset(s) with live counterparts.\n", count($mapping)));

        $relationCount = (new Query())
            ->from(Table::RELATIONS)
            ->where(['targetId' => array_keys($mapping)])
            ->count();

        $this->stdout("Relations rows to remap: {$relationCount}\n");

        $refTagRowCount = 0;
        if ($this->refTags) {
            $refTagRowCount = $this->countRefTagRows($mapping);
            $this->stdout("elements_sites rows with matching {asset:ID} tags: {$refTagRowCount}\n");
        } else {
            $this->stdout("(Pass --ref-tags to also scan CKEditor/rich-text asset reference tags.)\n");
        }

        if ($this->dryRun) {
            $this->stdout("\nDry run — no changes made. Re-run without --dry-run to apply.\n");
            return ExitCode::OK;
        }

        if (!$this->confirm("\nApply the remap?")) {
            $this->stdout("Aborted.\n");
            return ExitCode::OK;
        }

        $updatedRelations = $this->remapRelations($mapping);
        $this->stdout("Updated relations rows: {$updatedRelations}\n");

        if ($this->refTags) {
            $rewrittenRows = $this->rewriteRefTags($mapping);
            $this->stdout("Rewritten elements_sites rows: {$rewrittenRows}\n");
        }

        $this->stdout("Done. Old asset rows remain soft-deleted and will be removed by Craft's garbage collector per softDeleteDuration.\n");

        return ExitCode::OK;
    }

    public function actionHyper(): int
    {
        $mapping = $this->buildAssetIdMapping();

        if (empty($mapping)) {
            $this->stdout("No deleted→live asset mappings found for Cloudinary volumes.\n");
            return ExitCode::OK;
        }

        $this->stdout(sprintf("Loaded %d soft-deleted→live asset mapping(s).\n", count($mapping)));

        $rows = (new Query())
            ->select(['id', 'elementId', 'siteId', 'content'])
            ->from(Table::ELEMENTS_SITES)
            ->where(['like', 'content', '%hyper%links%', false])
            ->all();

        $this->stdout(sprintf("Scanning %d elements_sites row(s) containing Hyper links...\n", count($rows)));

        $rowsAffected = 0;
        $totalRefsRemapped = 0;
        $rowUpdates = [];

        foreach ($rows as $row) {
            $content = json_decode((string) $row['content'], true);

            if (!is_array($content)) {
                continue;
            }

            $rowRefsRemapped = $this->rewriteHyperLinksInContent($content, $mapping);

            if ($rowRefsRemapped > 0) {
                $rowsAffected++;
                $totalRefsRemapped += $rowRefsRemapped;
                $rowUpdates[] = [
                    'id' => (int) $row['id'],
                    'elementId' => (int) $row['elementId'],
                    'siteId' => (int) $row['siteId'],
                    'refs' => $rowRefsRemapped,
                    'content' => $content,
                ];
            }
        }

        $this->stdout("Rows to modify: {$rowsAffected}\n");
        $this->stdout("Asset references to remap: {$totalRefsRemapped}\n");

        if ($this->dryRun) {
            foreach ($rowUpdates as $u) {
                $this->stdout(sprintf("  element %d (site %d): %d ref(s)\n", $u['elementId'], $u['siteId'], $u['refs']));
            }
            $this->stdout("\nDry run — no changes made. Re-run without --dry-run to apply.\n");
            return ExitCode::OK;
        }

        if (!$this->confirm("\nApply the Hyper content rewrite?")) {
            $this->stdout("Aborted.\n");
            return ExitCode::OK;
        }

        $db = Craft::$app->getDb();
        foreach ($rowUpdates as $u) {
            $db->createCommand()
                ->update(
                    Table::ELEMENTS_SITES,
                    ['content' => new JsonExpression($u['content'])],
                    ['id' => $u['id']]
                )
                ->execute();
        }

        $this->stdout("Updated rows: {$rowsAffected}. Remapped refs: {$totalRefsRemapped}.\n");
        $this->stdout("You may want to run `php craft clear-caches/all` and purge Blitz.\n");

        return ExitCode::OK;
    }

    /**
     * Walks every string value in the outer content array that looks like a
     * Hyper-serialized array of links and rewrites any embedded asset IDs that
     * appear in the mapping. Mutates $content in place. Returns the number of
     * individual asset-ID substitutions made.
     *
     * @param array<int, int> $mapping old asset ID → new asset ID
     */
    private function rewriteHyperLinksInContent(array &$content, array $mapping): int
    {
        $refsRemapped = 0;

        foreach ($content as $key => $value) {
            if (!is_string($value) || $value === '' || $value[0] !== '[') {
                continue;
            }

            $links = json_decode($value, true);
            if (!is_array($links) || empty($links)) {
                continue;
            }

            $firstType = $links[0]['type'] ?? null;
            if (!is_string($firstType) || !str_contains($firstType, 'hyper')) {
                continue;
            }

            $keyChanged = false;

            foreach ($links as $linkIdx => $link) {
                if (!is_array($link) || !isset($link['fields']) || !is_array($link['fields'])) {
                    continue;
                }

                foreach ($link['fields'] as $subHandle => $subValue) {
                    if (!is_array($subValue)) {
                        continue;
                    }

                    foreach ($subValue as $i => $item) {
                        if (!is_scalar($item) || !is_numeric($item)) {
                            continue;
                        }

                        $intId = (int) $item;
                        if (!isset($mapping[$intId])) {
                            continue;
                        }

                        $newId = $mapping[$intId];
                        $subValue[$i] = is_string($item) ? (string) $newId : $newId;
                        $refsRemapped++;
                        $keyChanged = true;
                    }

                    $links[$linkIdx]['fields'][$subHandle] = $subValue;
                }
            }

            if ($keyChanged) {
                $content[$key] = json_encode($links);
            }
        }

        return $refsRemapped;
    }

    /**
     * @return array<int, int> map of old (soft-deleted) asset ID → live asset ID
     */
    private function buildAssetIdMapping(): array
    {
        $volumeIds = [];
        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            if ($volume->getFs() instanceof CloudinaryFs) {
                $volumeIds[] = $volume->id;
            }
        }

        if (empty($volumeIds)) {
            return [];
        }

        $liveByIdentity = [];
        $liveRows = (new Query())
            ->select(['assets.id', 'assets.folderId', 'assets.filename', 'assets.volumeId'])
            ->from(['assets' => Table::ASSETS])
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[assets.id]]')
            ->where(['assets.volumeId' => $volumeIds, 'elements.dateDeleted' => null])
            ->all();

        foreach ($liveRows as $row) {
            $key = $row['volumeId'] . ':' . $row['folderId'] . ':' . $row['filename'];
            $liveByIdentity[$key] = (int) $row['id'];
        }

        $deletedRows = (new Query())
            ->select(['assets.id', 'assets.folderId', 'assets.filename', 'assets.volumeId'])
            ->from(['assets' => Table::ASSETS])
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[assets.id]]')
            ->where(['assets.volumeId' => $volumeIds])
            ->andWhere(['not', ['elements.dateDeleted' => null]])
            ->all();

        $mapping = [];
        foreach ($deletedRows as $row) {
            $key = $row['volumeId'] . ':' . $row['folderId'] . ':' . $row['filename'];
            if (isset($liveByIdentity[$key])) {
                $oldId = (int) $row['id'];
                $newId = $liveByIdentity[$key];
                if ($oldId !== $newId) {
                    $mapping[$oldId] = $newId;
                }
            }
        }

        return $mapping;
    }

    private function remapRelations(array $mapping): int
    {
        $db = Craft::$app->getDb();
        $total = 0;

        foreach ($mapping as $oldId => $newId) {
            $total += (int) $db->createCommand()
                ->update(Table::RELATIONS, ['targetId' => $newId], ['targetId' => $oldId])
                ->execute();
        }

        return $total;
    }

    private function countRefTagRows(array $mapping): int
    {
        $where = ['or'];
        foreach (array_keys($mapping) as $oldId) {
            $where[] = ['like', 'content', '{asset:' . $oldId, false];
        }

        return (int) (new Query())
            ->from(Table::ELEMENTS_SITES)
            ->where($where)
            ->count();
    }

    private function rewriteRefTags(array $mapping): int
    {
        $db = Craft::$app->getDb();

        $where = ['or'];
        foreach (array_keys($mapping) as $oldId) {
            $where[] = ['like', 'content', '{asset:' . $oldId, false];
        }

        $rows = (new Query())
            ->select(['elementId', 'siteId', 'content'])
            ->from(Table::ELEMENTS_SITES)
            ->where($where)
            ->all();

        $count = 0;
        foreach ($rows as $row) {
            $content = $row['content'];

            if ($content === null || $content === '') {
                continue;
            }

            $modified = $content;
            foreach ($mapping as $oldId => $newId) {
                $modified = preg_replace(
                    '/\{asset:' . preg_quote((string) $oldId, '/') . '(?=[@:|}])/',
                    '{asset:' . $newId,
                    $modified
                );
            }

            if ($modified !== $content) {
                $db->createCommand()
                    ->update(
                        Table::ELEMENTS_SITES,
                        ['content' => $modified],
                        ['elementId' => $row['elementId'], 'siteId' => $row['siteId']]
                    )
                    ->execute();
                $count++;
            }
        }

        return $count;
    }
}

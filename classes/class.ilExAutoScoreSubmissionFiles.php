<?php
declare(strict_types=1);

// Copyright (c) 2026 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\Filesystem\Stream\Streams;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Submitted files and tutor feedback files in the resource storage (ILIAS 10+)
 */
class ilExAutoScoreSubmissionFiles
{
    /**
     * @return array[] keys: returned_id, filetitle, user_id, team_id, timestamp, rid
     */
    public static function getFiles(ilExSubmission $submission): array
    {
        global $DIC;

        $manager = $DIC->exercise()->internal()->domain()->submission($submission->getAssignment()->getId());
        $files = [];
        foreach ($manager->getSubmissionsOfUser($submission->getUserId()) as $sub) {
            if ($sub->getRid() === '') {
                continue;
            }
            $files[] = [
                'returned_id' => $sub->getId(),
                'filetitle' => $sub->getTitle(),
                'user_id' => $sub->getUserId(),
                'team_id' => $sub->getTeamId(),
                'timestamp' => $sub->getTimestamp(),
                'rid' => $sub->getRid(),
            ];
        }
        return $files;
    }

    public static function getSize(array $file): int
    {
        global $DIC;

        $irss = $DIC->resourceStorage();
        $rid = $irss->manage()->find((string) $file['rid']);
        if ($rid === null) {
            return 0;
        }
        return $irss->manage()->getCurrentRevision($rid)->getInformation()->getSize();
    }

    /**
     * Copy the file content to a new temporary file, the caller has to delete it
     */
    public static function copyToTemp(ilExAssignment $assignment, array $file): ?string
    {
        global $DIC;

        $stream = $DIC->exercise()->internal()->repo()->submission()->getStream($assignment->getId(), (string) $file['rid']);
        if ($stream === null) {
            return null;
        }
        $path = ilFileUtils::ilTempnam();
        $target = fopen($path, 'wb');
        stream_copy_to_stream($stream->detach(), $target);
        fclose($target);
        return $path;
    }

    public static function addUpload(ilExSubmission $submission, UploadResult $result): bool
    {
        global $DIC;

        return $DIC->exercise()->internal()->domain()->submission($submission->getAssignment()->getId())
            ->addUpload($submission->getUserId(), $result);
    }

    /**
     * Add a file without deadline and max file checks, e.g. when a tutor changes teams
     */
    public static function addLocalFile(ilExAssignment $assignment, int $user_id, int $team_id, string $path, string $title): bool
    {
        global $DIC;

        return $DIC->exercise()->internal()->repo()->submission()->addLocalFile(
            $assignment->getExerciseId(),
            $assignment->getId(),
            $user_id,
            $team_id,
            $path,
            $title,
            false,
            new ilExcSubmissionStakeholder()
        );
    }

    /**
     * @param UploadedFileInterface[] $files
     */
    public static function replaceFeedbackFiles(ilExAssignment $assignment, int $participant_id, array $files): void
    {
        global $DIC;

        $irss = $DIC->resourceStorage();
        $manager = $DIC->exercise()->internal()->domain()->assignment()->tutorFeedbackFile($assignment->getId());
        $stakeholder = $manager->getStakeholder();
        $repo = $assignment->getAssignmentType()->usesTeams()
            ? $DIC->exercise()->internal()->repo()->tutorFeedbackFileTeam()
            : $DIC->exercise()->internal()->repo()->tutorFeedbackFile();

        if (!$manager->hasCollection($participant_id)) {
            $manager->createCollection($participant_id);
        }
        $collection = $repo->getCollection($assignment->getId(), $participant_id);
        if ($collection === null) {
            return;
        }

        foreach ($collection->getResourceIdentifications() as $rid) {
            $collection->remove($rid);
            $irss->manage()->remove($rid, $stakeholder);
        }
        foreach ($files as $file) {
            $collection->add($irss->manage()->stream(
                Streams::ofPsr7Stream($file->getStream()),
                $stakeholder,
                ilFileUtils::getASCIIFilename((string) $file->getClientFilename())
            ));
        }
        $irss->collection()->store($collection);
    }

    public static function deleteFeedbackFiles(ilExAssignment $assignment, int $participant_id): void
    {
        global $DIC;

        $manager = $DIC->exercise()->internal()->domain()->assignment()->tutorFeedbackFile($assignment->getId());
        if ($manager->hasCollection($participant_id)) {
            $manager->deleteCollection($participant_id);
        }
    }
}

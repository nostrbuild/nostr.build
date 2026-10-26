<?php

/**
 * Upload refusals that are the client's fault, so the routes answer a 4xx
 * instead of "Server error, please try again later" (500), which made clients
 * retry a file that can never be stored (blossom mirrors, 2026-10-25). Thrown
 * only by ImageProcessor with $clientFile (the client's own upload), never
 * for a file the server produced, whose faults stay 500. detectFileExt's
 * "type not accepted" stays an InvalidArgumentException: only the callers
 * that check the client's own bytes (UploadValidator, the profile picture
 * path, S3Multipart's multipart completion) answer it with 415.
 */

/**
 * A file whose type nostr.build doesn't accept: 415. A subclass of
 * InvalidArgumentException, so the callers that catch that still do.
 */
class UnsupportedFileType extends InvalidArgumentException
{
}

/**
 * A file that can't be read as what it claims to be (a damaged or truncated
 * image): 422.
 */
class UnreadableFile extends RuntimeException
{
}

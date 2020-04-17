<?php


namespace Ling\TemporaryVirtualFileSystem;


use Ling\BabyYaml\BabyYamlUtil;
use Ling\Bat\CaseTool;
use Ling\Bat\FileSystemTool;
use Ling\TemporaryVirtualFileSystem\Exception\TemporaryVirtualFileSystemException;

/**
 * The TemporaryVirtualFileSystem class.
 *
 * With this class, I store files in @page(babyYaml) files, each context dir looks like this:
 *
 *
 * - $contextDir/
 * ----- operations.byml
 * ----- files/
 *
 *
 *
 *
 * The operations.byml file contains the operations to return when the commit method is called (i.e. redundant information
 * is handled). It's an array of operations, each operation:
 *
 * - type: string (add|remove|update). The operation type (to execute on the real system).
 * - url: string. The file url which serves as an identifier. It should always start with a slash (for now).
 * - path: string. The relative path (from the contextDir's files directory) to the uploaded file
 * - meta: array. An array of meta containing whatever you want.
 *
 *
 *
 * Heuristics
 * -----------
 *
 * See the heuristic section of the @page(TemporaryVirtualFileSystem conception notes).
 *
 * For the **add** operation, in case an add operation already exists with the same url, we update the operation (rather than
 * rejecting the request).
 *
 *
 *
 *
 *
 */
abstract class TemporaryVirtualFileSystem implements TemporaryVirtualFileSystemInterface
{

    /**
     * This property holds the rootDir for this instance.
     * @var string
     */
    protected $rootDir;


    /**
     * Builds the TemporaryVirtualFileSystem instance.
     */
    public function __construct()
    {
        $this->rootDir = '/tmp';
    }

    /**
     * Sets the rootDir.
     *
     * @param string $rootDir
     */
    public function setRootDir(string $rootDir)
    {
        $this->rootDir = $rootDir;
    }



    //--------------------------------------------
    //
    //--------------------------------------------
    /**
     * @implementation
     */
    public function getRootDir(): string
    {
        return $this->rootDir;
    }


    /**
     * @implementation
     */
    public function reset(string $contextId)
    {
        FileSystemTool::remove($this->getContextDir($contextId));
    }

    /**
     * @implementation
     */
    public function commit(string $contextId): array
    {
        // TODO: Implement commit() method.
    }

    /**
     * @implementation
     */
    public function get(string $contextId, string $url): array
    {
        return $this->getEntry($contextId, $url);
    }

    /**
     * @implementation
     */
    public function has(string $contextId, string $url, array $allowedTypes = null): bool
    {
        return $this->hasEntry($contextId, $url, $allowedTypes);
    }


    /**
     * @implementation
     */
    public function add(string $contextId, string $path, array $meta): array
    {
        $url = $this->getFileUrl($contextId, $path, $meta);
        return $this->addEntry($contextId, $url, $path, $meta);
    }


    /**
     * @implementation
     */
    public function remove(string $contextId, string $url)
    {
        $this->removeEntry($contextId, $url);
    }


    /**
     * @implementation
     */
    public function update(string $contextId, string $url, string $path, array $meta)
    {
        $this->updateEntry($contextId, $url, $path, $meta);
    }



    //--------------------------------------------
    //
    //--------------------------------------------
    /**
     * Adds an entry to the operations.byml file of the given context, and returns the added entry.
     *
     *
     * The options are (all optional):
     *
     * - type: string. The possible values are:
     *      - update: means that the operation is an update for a file that is not registered yet in the vfs (but probably exists
     *          on the real server)
     *
     *
     * @param string $contextId
     * @param string $url
     * @param string $path
     * @param array $meta
     * @param array $options
     * @return array
     */
    protected function addEntry(string $contextId, string $url, string $path, array $meta, array $options = []): array
    {
        $path = $this->getRealPath($path);

        $type = $options['type'] ?? 'add';


        $opFile = $this->getContextDir($contextId) . "/operations.byml";
        $ops = [];
        if (true === file_exists($opFile)) {
            $ops = BabyYamlUtil::readFile($opFile);
        }


        $relPath = $this->getFileRelativePath($contextId, $url, $path, $meta);
        FileSystemTool::copyFile($path, $this->getContextDir($contextId) . "/files/" . $relPath);

        $addOperation = [
            'type' => $type,
            'url' => $url,
            'path' => $relPath,
            'meta' => $meta,
        ];


        //--------------------------------------------
        // ADDING THE OPERATION (see heuristic notes for more details)
        //--------------------------------------------
        $found = false;
        foreach ($ops as $k => $op) {
            if ($url === $op['url']) {
                $found = true;
                $type = $op['type'];
                switch ($type) {
                    case "add":
                        $ops[$k] = $addOperation;
                        break;
                    default:
                        $this->error("Operation \"$type\" rejected. You cannot add this entry because it already exists with type=\"$type\" for url \"$url\".");
                        break;
                }
            }
        }
        if (false === $found) {
            $ops[] = $addOperation;
        }


        BabyYamlUtil::writeFile($ops, $opFile);
        return $addOperation;
    }

    /**
     * Returns whether there is an non-deleted entry found in the the operations.byml file of the given context that matches the given url.
     *
     * @param string $contextId
     * @param string $url
     * @param array|null $allowedTypes
     * @return bool
     */
    protected function hasEntry(string $contextId, string $url, array $allowedTypes = null): bool
    {
        $opFile = $this->getOperationsFile($contextId);
        $ops = BabyYamlUtil::readFile($opFile);
        $types = (null === $allowedTypes) ? ["add", "remove", "update"] : $allowedTypes;

        foreach ($ops as $k => $op) {
            if (false === in_array($op['type'], $types)) {
                continue;
            }
            if ($url === $op['url']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Removes the entry from the operations.byml file of the given context that matches the given url.
     * If the entry didn't exist, the method will be silent.
     *
     * @param string $contextId
     * @param string $url
     */
    protected function removeEntry(string $contextId, string $url)
    {
        $opFile = $this->getOperationsFile($contextId);
        $ops = BabyYamlUtil::readFile($opFile);


        /**
         * If the entry is found, we remove it directly from the operations.
         */
        $addTheDeleteEntry = true;
        foreach ($ops as $k => $op) {
            if ($url === $op['url']) {
                $addTheDeleteEntry = false;
                $type = $op['type'];
                switch ($type) {
                    case "add":
                        unset($ops[$k]);
                        break;
                    case "update":
                        unset($ops[$k]);
                        $addTheDeleteEntry = true;
                        break;
                }
            }
        }


        if (true === $addTheDeleteEntry) {
            $ops[] = [
                'type' => "remove",
                'url' => $url,
            ];
        }

        $ops = array_merge($ops);
        BabyYamlUtil::writeFile($ops, $opFile);

    }

    /**
     * Updates the entry in the operations.byml file of the given context that matches the given url.
     *
     * Throws an exception if the file wasn't found, or in case of problems.
     *
     * @param string $contextId
     * @param string $url
     * @param string $path
     * @param array $meta
     */
    protected function updateEntry(string $contextId, string $url, string $path, array $meta)
    {
        $path = $this->getRealPath($path);

        $opFile = $this->getOperationsFile($contextId);
        $ops = BabyYamlUtil::readFile($opFile);


        $found = false;
        foreach ($ops as $k => $op) {
            if ($url === $op['url']) {
                $found = true;

                $relPath = $this->getFileRelativePath($contextId, $url, $path, $meta);
                FileSystemTool::copyFile($path, $this->getContextDir($contextId) . "/files/" . $relPath);


                $type = $op['type'];
                switch ($type) {
                    case "add":
                    case "update":
                        $op['meta'] = $meta;
                        $op['path'] = $relPath;
                        $ops[$k] = $op;
                        break;
                    case "remove":
                        $ops[$k] = [
                            "type" => "update",
                            "url" => $url,
                            "path" => $relPath,
                            "meta" => $meta,
                        ];
                        break;
                }


            }
        }

        if (false === $found) {
            $this->addEntry($contextId, $url, $path, $meta, [
                "type" => "update",
            ]);
        } else {
            $ops = array_merge($ops);
            BabyYamlUtil::writeFile($ops, $opFile);
        }
    }

    /**
     * Returns the entry in the operations.byml file of the given context that matches the given url.
     *
     * @param string $contextId
     * @param string $url
     * @return array
     * @throws \Exception
     */
    protected function getEntry(string $contextId, string $url): array
    {

        $opFile = $this->getOperationsFile($contextId);
        $ops = BabyYamlUtil::readFile($opFile);
        foreach ($ops as $op) {
            if ($url === $op['url']) {
                return $op;
            }
        }

        $this->error("Entry not found with url=\"$url\" and contextId=\"$contextId\".");

    }


    /**
     * Returns the context dir for the given context id.
     *
     * @param string $contextId
     * @return string
     */
    protected function getContextDir(string $contextId): string
    {
        return $this->rootDir . "/" . CaseTool::toPortableFilename($contextId);
    }


    /**
     * Creates the operations.byml file if necessary (for the given context id) and returns its path.
     *
     * @param string $contextId
     * @return string
     */
    protected function getOperationsFile(string $contextId): string
    {
        $opFile = $this->getContextDir($contextId) . "/operations.byml";
        if (false === file_exists($opFile)) {
            $ops = [];
            BabyYamlUtil::writeFile($ops, $opFile);
        }
        return $opFile;
    }


    //--------------------------------------------
    // EXTEND
    //--------------------------------------------
    /**
     * Returns the relative path (from the contextDir's files directory) of the uploaded file located by the given path.
     *
     *
     *
     * @param string $contextId
     * @param string $url
     * @param string $path
     * @param array $meta
     * @return string
     */
    protected function getFileRelativePath(string $contextId, string $url, string $path, array $meta): string
    {
        return trim($path, '/');
    }


    /**
     * Returns the file url for the file identified by the given parameters.
     *
     * @param string $contextId
     * @param string $path
     * @param array $meta
     * @return string
     */
    abstract protected function getFileUrl(string $contextId, string $path, array $meta): string;


    //--------------------------------------------
    //
    //--------------------------------------------
    /**
     * Throws an exception.
     *
     * @param string $msg
     * @throws \Exception
     */
    private function error(string $msg)
    {
        throw new TemporaryVirtualFileSystemException($msg);
    }


    /**
     * Returns the realpath of the given path.
     *
     * If the file doesn't exist, an exception is thrown.
     *
     * @param string $path
     * @return string
     * @throws \Exception
     */
    private function getRealPath(string $path): string
    {
        if (false === ($ret = realpath($path))) {
            $this->error("File does not exist: \"$path\".");
        }
        return $ret;
    }
}
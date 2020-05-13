[Back to the Ling/TemporaryVirtualFileSystem api](https://github.com/lingtalfi/TemporaryVirtualFileSystem/blob/master/doc/api/Ling/TemporaryVirtualFileSystem.md)<br>
[Back to the Ling\TemporaryVirtualFileSystem\TemporaryVirtualFileSystem class](https://github.com/lingtalfi/TemporaryVirtualFileSystem/blob/master/doc/api/Ling/TemporaryVirtualFileSystem/TemporaryVirtualFileSystem.md)


TemporaryVirtualFileSystem::onFileAddedAfter
================



TemporaryVirtualFileSystem::onFileAddedAfter — Hook called after the file has been added to the virtual file system.




Description
================


protected [TemporaryVirtualFileSystem::onFileAddedAfter](https://github.com/lingtalfi/TemporaryVirtualFileSystem/blob/master/doc/api/Ling/TemporaryVirtualFileSystem/TemporaryVirtualFileSystem/onFileAddedAfter.md)(string $contextId, array &$operation, string $path, string $dst, ?array $options = []) : void




Hook called after the file has been added to the virtual file system.


- operation is the entry representing the file operation.
- path is the absolute path to the source file being copied;
- dst is the absolute path to the copied file.




Parameters
================


- contextId

    

- operation

    

- path

    

- dst

    

- options

    


Return values
================

Returns void.








Source Code
===========
See the source code for method [TemporaryVirtualFileSystem::onFileAddedAfter](https://github.com/lingtalfi/TemporaryVirtualFileSystem/blob/master/TemporaryVirtualFileSystem.php#L516-L519)


See Also
================

The [TemporaryVirtualFileSystem](https://github.com/lingtalfi/TemporaryVirtualFileSystem/blob/master/doc/api/Ling/TemporaryVirtualFileSystem/TemporaryVirtualFileSystem.md) class.

Previous method: [getFileRelativePath](https://github.com/lingtalfi/TemporaryVirtualFileSystem/blob/master/doc/api/Ling/TemporaryVirtualFileSystem/TemporaryVirtualFileSystem/getFileRelativePath.md)<br>Next method: [getFileId](https://github.com/lingtalfi/TemporaryVirtualFileSystem/blob/master/doc/api/Ling/TemporaryVirtualFileSystem/TemporaryVirtualFileSystem/getFileId.md)<br>


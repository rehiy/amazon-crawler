@ECHO OFF

CD /d %~dp0

::设置系统环境

SET "PATH=.\node_modules\.bin;%PATH%"
SET "PATH=%PATH%;D:\Software\PortableGit\bin"

::加载PHP7环境

IF EXIST D:\RunTime\php7\runtime.bat (
    CALL D:\RunTime\php7\runtime set "%~n0"
)

:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

::编译项目

CD /d %~dp0
IF "%1" == "" CMD /k

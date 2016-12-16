CAS Authenticate
================


Extension parameters
--------------------

- serverVersion : The version of the CAS server
- serverHostname : The hostname of the CAS server
- serverPort : The port the CAS server is running on
- serverUri : The URI the CAS server is responding on
- changeSessionID : Allow phpCAS to change the session_id (Single Sign Out/handleLogoutRequests is based on that change)
- casServerCACertificate : The serviceValidate URL
- certificateDirectory : Emplacement TYPO3 des certificats à utiliser
- serverCACert : Certificat CA du serveur CAS filename
- userGroupIdList : List of comma-separated fegroups to assign after fe_user creation
- userPid : Feusers storage pid
- phpCasSetDebug : Enable phpCAS debugging:0 : Disabled, -1: Enabled


How it works
------------

This extension allow frontend users to connect through an external CAS server. It declare :
- an authentication service called during login phase (?logintype=login). This service :
    - call the CAS server to make a redirection to it's login form
    - in case of success, the user comes back to the website home page and the authentication process continue
    - the user is automatically created if he doesn't exists
- a hook called during logout phase (?logintype=logout) for CAS disconnecting
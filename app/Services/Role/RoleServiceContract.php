<?php
namespace Modules\Core\Role;
interface RoleServiceContract
{

  public function listAllRoles();

  public function allPermissions();

  public function allRoles();

  public function permissionsUpdate($requestData);

  public function create($requestData);

  public function destroy($id);
}

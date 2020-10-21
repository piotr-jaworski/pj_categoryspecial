<?php
namespace PjCategorySpecial\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PjCategorySpecial\Entity\CategorySpecial;

class CategorySpecialController extends FrameworkBundleAdminController
{
    public function toggleIsSpecialAction($categoryId)
    {
        try {
            CategorySpecial::toggleSpecial($categoryId);
            $resp = ['status' => true, 'message' => $this->trans('Successful update.', 'Admin.Notifications.Success')];
        } catch (Exception $e) {
            $resp = ['status' => false, 'message' => $e->getMessage()];
        }
        return $this->json($resp);
    }
}
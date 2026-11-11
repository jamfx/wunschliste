<?php
declare(strict_types=1);

class InstallController
{
    /** GET /install — redirect to install.php */
    public function index(array $params = []): void
    {
        if (file_exists(CONFIG_PATH)) {
            flash('error', __('install.already_installed'));
            redirect(base_url('admin/login'));
        }
        redirect(base_url('install.php'));
    }

    /** GET / — temporary placeholder shown until EventController is implemented (Phase 3) */
    public function placeholder(array $params = []): void
    {
        $pageTitle = __('app.title');
        render('placeholder');
    }
}

<?php
namespace AOE\Tagging\Command;

use AOE\Tagging\Vcs\Driver\GitDriver;
use AOE\Tagging\Vcs\Version;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package AOE\Tagging\Command
 */
class GitCommand extends Command
{
    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('git')
            ->setDescription('Tagging a GIT Repository')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'The URL to the repository'
            )
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'The path to the cloned repository'
            )
            ->addOption(
                'version-type',
                'vt',
                InputOption::VALUE_REQUIRED,
                'define the version type which will be used to increment (major, minor or patch)',
                Version::INCREASE_PATCH
            )
            ->addOption(
                'evaluate',
                'e',
                InputOption::VALUE_NONE,
                'If set only the next version will outputted. If not changes detected, output is empty.'
            )
            ->addOption(
                'commit-and-push',
                'cap',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'define files which should commited and pushed before creating a tag'
            )
            ->addOption(
                'message',
                'm',
                InputOption::VALUE_REQUIRED,
                'commit message if "commit-and-push" is used',
                ''
            )
            ->addOption(
                'from-version',
                'fm',
                InputOption::VALUE_REQUIRED,
                'If set, the new version will be generated from beginning of this given version number.',
                ''
            )
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_REQUIRED,
                'Branch-Name: which will be use for tagging the branch',
                'master'
            )
            ->addOption(
                'switch-branch',
                'swb',
                InputOption::VALUE_NONE,
                'Option that triggers the git client to switch to the specified branch'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $git = $this->getDriver($input->getArgument('url'));
        $branch = $input->getOption('branch');
        $path = $input->getArgument('path');

        if ($input->getOption('switch-branch')) {
            $git->checkoutBranch($branch, $path, $output);
            return;
        }

        $version = new Version();
        $latest = $this->getLatestVersion($input, $git);
        $next = $version->increase($latest, $input->getOption('version-type'));

        if ($input->getOption('evaluate')) {
            if ($git->hasChangesSinceTag($latest, $branch, $path, $output)) {
                $output->write($next);
            }
            return;
        }

        if ($git->hasChangesSinceTag($latest, $branch, $path, $output)) {
            $this->handleChanges($input, $output, $git, $path, $latest, $next, $branch);
        } else {
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln(
                    sprintf(
                        '<info>Skip creating tag "%s" because there are no changes since tag "%s"</info>',
                        $next,
                        $latest
                    )
                );
            }
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param GitDriver $git
     * @param $path
     * @param $latest
     * @param $next
     * @param $branch
     * @throws \Exception
     */
    private function handleChanges(
        InputInterface $input,
        OutputInterface $output,
        GitDriver $git,
        $path,
        $latest,
        $next,
        $branch
    ) {
        if ($input->getOption('commit-and-push')) {
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln(
                    '<info>commit and push: "' . implode(' ', $input->getOption('commit-and-push')) . '"</info>'
                );
            }
            $git->commit(
                $input->getOption('commit-and-push'),
                $path,
                $input->getOption('message')
            );
        }

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln('<info>Latest Tag number is "' . $latest . '"</info>');
            $output->writeln('<info>Next Tag number is "' . $next . '"</info>');
        }

        $git->tag($next, $branch, $path);
    }

    /**
     * @param InputInterface $input
     * @param GitDriver $git
     * @return mixed|string
     */
    private function getLatestVersion(InputInterface $input, GitDriver $git)
    {
        if ($input->getOption('from-version')) {
            return $latest = $input->getOption('from-version');
        } else {
            return $latest = $git->getLatestTag();
        }
    }

    /**
     * @param string $url
     * @return GitDriver
     */
    protected function getDriver($url)
    {
        return new GitDriver($url);
    }
}

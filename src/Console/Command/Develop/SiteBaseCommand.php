<?php

/**
 * @file
 * Contains \VM\Console\Develop\SiteBaseCommand.
 *
 * Base class for site commands.
 */

namespace VM\Console\Command\Develop;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Drupal\Console\Command\Shared\CommandTrait;
use Drupal\Console\Style\DrupalStyle;
use Drupal\Console\Config;
use VM\Console\Command\Exception\SiteCommandException;

/**
 * Class SiteBaseCommand
 *
 * @package VM\Console\Command\Develop
 */
class SiteBaseCommand extends Command {
  use CommandTrait;
  /**
   * IO interface.
   *
   * @var null
   */
  protected $io = NULL;
  /**
   * Global location for sites.yml.
   *
   * @var array
   */
  protected $configFile = NULL;
  /**
   * Stores the contents of sites.yml.
   *
   * @var array
   */
  protected $config = NULL;
  /**
   * Stores the site name.
   *
   * @var string
   */
  protected $siteName = NULL;
  /**
   * Stores the profile name.
   *
   * @var string
   */
  protected $profile = NULL;
  /**
   * Stores the destination directory.
   *
   * @var string
   */
  protected $destination = NULL;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('site_base');

    $this->addArgument(
      'name',
      InputArgument::REQUIRED,
      // @todo use: $this->trans('commands.site.checkout.name.description')
      'The site name that is mapped to a repo in sites.yml'
    );

    $this->addOption(
      'destination-directory',
      '-D',
      InputOption::VALUE_OPTIONAL,
      // @todo use: $this->trans('commands.site.checkout.name.options')
      'Specify the destination of the site if different than the global destination found in sites.yml'
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {

  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->validateSiteParams($input, $output);
  }

  /**
   * Helper to copy the arguments from another class.
   */
  public function inheritArguments(\Symfony\Component\Console\Command\Command $command) {
    foreach ($command->getDefinition()->getArguments() as $argument) {
      if (!$this->getDefinition()->hasArgument($argument->getName())) {
        $this->getDefinition()->addArgument($argument);
      }
    }
  }

  /**
   * Helper to copy the options from another class.
   */
  public function inheritOptions(\Symfony\Component\Console\Command\Command $command) {
    foreach ($command->getDefinition()->getOptions() as $option) {
      if (!$this->getDefinition()->hasOption($option->getName())) {
        $this->getDefinition()->addOption($option);
      }
    }
  }

  /**
   * Helper to validate parameters.
   *
   * @param InputInterface  $input
   * @param OutputInterface $output
   */
  protected function validateSiteParams(InputInterface $input, OutputInterface $output) {
    $this->io = new DrupalStyle($input, $output);

    // Get config.
    $this->_getConfigFile();

    // Validate site name.
    $this->_validateSiteName($input);

    // Validate profile.
    $this->_validateProfile($input);

    // Validate destination.
    $this->_validateDestination($input);
  }

  /**
   * Helper to check that the config file exits.
   *
   * @return $config The configuration from the yml file
   *
   * @throws SiteCommandException
   */
  protected function _getConfigFile() {
    $ymlFile = new Parser();
    $config = new Config($ymlFile);
    $configFile = $config->getUserHomeDir() . '/.console/sites.yml';

    if (!file_exists($configFile)) {
      $message = sprintf('Could not find any configuration in %s', $configFile);
      throw new SiteCommandException($message);
    }
    $this->configFile = $configFile;
    $this->config = $config->getFileContents($configFile);

    return $this->configFile;
  }

  /**
   * Helper to validate name parameter.
   *
   * @param InputInterface $input
   *
   * @throws SiteCommandException
   *
   * @return string Site name.
   */
  protected function _validateSiteName(InputInterface $input) {
    $this->siteName = $input->getArgument('name');
    if (!isset($this->config['sites'][$this->siteName])) {
      $message = sprintf(
        'Site not found in /.console/sites.yml' . PHP_EOL .
        'Usage: drupal site:checkout name' . PHP_EOL .
        'Available sites: [%s]',
        implode(', ', array_keys($this->config['sites']))
      );
      throw new SiteCommandException($message);
    };

    // Update input.
    if ($input->hasArgument('name')) {
      $input->setArgument('name', $this->siteName);
    }

    return $this->siteName;
  }

  /**
   * Helper to validate profile parameter.
   *
   * @param InputInterface $input
   *
   * @return string Profile
   */
  protected function _validateProfile(InputInterface $input) {
    if ($input->hasArgument('profile') &&
      !is_null($input->getArgument('profile'))
    ) {
      // Use config from parameter.
      $this->profile = $input->getArgument('profile');
    }
    elseif (isset($this->config['sites'][$this->siteName]['profile'])) {
      // Use config from sites.yml.
      $this->profile = $this->config['sites'][$this->siteName]['profile'];
    }
    else {
      $this->profile = 'config_installer';
    }

    // Update input.
    if ($input->hasArgument('profile')) {
      $input->setArgument('profile', $this->profile);
    }

    return $this->profile;
  }

  /**
   * Helper to validate destination parameter.
   *
   * @param InputInterface $input
   *
   * @throws SiteCommandException
   *
   * @return string Destination
   */
  protected function _validateDestination(InputInterface $input) {
    if ($input->hasOption('destination-directory') &&
      !is_null($input->getOption('destination-directory'))
    ) {
      // Use config from parameter.
      $this->destination = $input->getOption('destination-directory');
    }
    elseif (isset($this->config['global']['destination-directory'])) {
      // Use config from sites.yml.
      $this->destination = $this->config['global']['destination-directory'] .
        '/' . $this->siteName;
    }
    else {
      $this->destination = '/tmp/' . $this->siteName;
    }

    // Make sure we have a slash at the end.
    if (substr($this->destination, -1) != '/') {
      $this->destination .= '/';
    }

    // Append site name.
    if (strpos($this->destination, $this->siteName, 0) === FALSE) {
      $this->destination .= $this->siteName . '/';
    }

    // Update input.
    if ($input->hasOption('destination-directory')) {
      // This breaks the chain command.
      // $input->setOption('destination-directory', $this->destination);
    }

    return $this->destination;
  }
}
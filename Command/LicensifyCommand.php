<?php

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\CommandBundle\Command;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\HttpKernel\Util\Filesystem;

class LicensifyCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('licensify');
        $this->addArgument('bundle', InputArgument::REQUIRED, 'The bundle which should be licensified.');
        $this->addOption('license', null, InputArgument::OPTIONAL, 'The license to use', 'Apache2');
        $this->addOption('author', null, InputArgument::OPTIONAL, 'The author to use.', 'Johannes M. Schmitt <schmittjoh@gmail.com>');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $kernel = $this->getContainer()->get('kernel');
        $bundle = $kernel->getBundle($input->getArgument('bundle'));
        $author = $input->getOption('author');

        ob_start();
        require $kernel->locateResource('@JMSCommandBundle/Resources/skeleton/License/'.$input->getOption('license'));
        $license = ob_get_clean();

        ob_start();
        require $kernel->locateResource('@JMSCommandBundle/Resources/skeleton/License/'.$input->getOption('license').'_full');
        $fullLicense = ob_get_clean();

        foreach (Finder::create()->name('*.php')->in($bundle->getPath()) as $file) {
            $tokens = token_get_all(file_get_contents($file->getPathname()));

            $content = '';
            $afterNamespace = $afterClass = $ignoreWhitespace = false;
            for ($i=0, $c=count($tokens); $i<$c; $i++) {
                if (!is_array($tokens[$i])) {
                    $content .= $tokens[$i];
                    continue;
                }

                if ($ignoreWhitespace && T_WHITESPACE === $tokens[$i][0]) {
                    continue;
                }
                $ignoreWhitespace = false;

                if (!$afterNamespace && (T_COMMENT === $tokens[$i][0] || T_WHITESPACE === $tokens[$i][0])) {
                    continue;
                }

                if (T_NAMESPACE === $tokens[$i][0]) {
                    $content .= "\n".$license."\n\n";
                    $afterNamespace = true;
                }

                if (!$afterClass && T_COMMENT === $tokens[$i][0]) {
                    $ignoreWhitespace = true;
                    continue;
                }

                if (T_CLASS === $tokens[$i][0]) {
                    $afterClass = true;
                }

                $content .= $tokens[$i][1];
            }

            if ($afterNamespace === false) {
                continue;
            }

            file_put_contents($file->getPathname(), $content);
        }

        // Add LICENSE file in Resources/meta/LICENSE
        $metaPath   = $bundle->getPath() . '/Resources/meta';
        $metaFile   = $metaPath . '/LICENSE';

        if (!is_dir($metaPath)) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($metaPath);
        }

        file_put_contents($metaFile, $fullLicense);

        if (!file_exists($metaFile)) {
            $output->writeln(sprintf('[File+] <comment>%s</comment>', $metaFile));
        } else {
            $output->writeln(sprintf('[Modify] <comment>%s</comment>', $metaFile));
        }
    }
}

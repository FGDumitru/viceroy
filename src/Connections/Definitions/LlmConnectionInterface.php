<?php

namespace Viceroy\Connections\Definitions;

use Viceroy\Core\Response;

interface LlmConnectionInterface {

  /**
   * @return bool
   */
  public function health(): bool;

  /**
   * @param string $sentence
   *
   * @return array|bool
   */
  public function tokenize(string $sentence): array|bool;

  /**
   * @param array $promptJson
   *
   * @return string|bool
   */
  public function detokenize(array $promptJson): string|bool;

  /**
   * @param array $promptJson
   *
   * @return \Viceroy\Core\Response|bool
   */
  public function queryPost(array $promptJson = []): Response|bool;

}
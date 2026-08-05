// @ts-check

import { addFilter } from "@wordpress/hooks";
import { createHigherOrderComponent } from "@wordpress/compose";

/**
 * Is the url external to this site?
 *
 * @param {string | undefined} url
 * @returns {boolean}
 */

const isExternal = (url) =>
  !!url && /^https?:\/\//.test(url) && !url.startsWith(window.location.origin);

/**
 * Adds a warning class to core/image blocks that reference an external image.
 *
 * Implemented as a BlockListBlock filter (rather than querying the DOM) so the
 * class is rendered inside the editor canvas even when it is iframed (WP 7.0+).
 */

const withExternalImageWarning = createHigherOrderComponent(
  (BlockListBlock) => (props) =>
    props.name === "core/image" && isExternal(props.attributes?.url) ? (
      <BlockListBlock {...props} className="external-image-warning" />
    ) : (
      <BlockListBlock {...props} />
    ),
  "withExternalImageWarning",
);

addFilter(
  "editor.BlockListBlock",
  "moj/external-image-warning",
  withExternalImageWarning,
);

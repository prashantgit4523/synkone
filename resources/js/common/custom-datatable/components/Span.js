import React from 'react';

const Span = ({isHTML = false, content, ...rest}) => isHTML ?
    <span dangerouslySetInnerHTML={{__html: content}} {...rest}/> : <span {...rest}>{content}</span>;

export default Span;
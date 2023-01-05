import React, {Fragment} from 'react';

import Span from "./Span";

const Cell = ({row, visibleColumns, index, column: {canOverflow = false, isHTML = false, CustomComponent, accessor, minWidth}}) => {
    if (!visibleColumns.includes(accessor)) return <Fragment key={`cell-empty-${index}`}/>;
    return (
        <td
            scope="row"
            style={{minWidth, overflow: canOverflow ? 'visible' : 'hidden'}}
        >
            {CustomComponent ? <CustomComponent row={row}/> : <Span isHTML={isHTML} content={row[accessor]}/>}
        </td>
    )
}

export default Cell;
import React, {memo} from 'react';

import {useDispatch, useSelector} from "react-redux";
import {toggleRow, toggleRowSelection, toggleSorting} from "../../../store/slices/dataTableSlice";

import Cell from './Cell';
import Checkbox from "./Checkbox";
import CollapseRowIcon from "./CollapseRowIcon";
import ToggleRowIcon from "./ToggleRowIcon";
import SortAscendingIcon from "./SortAscendingIcon";
import SortDescendingIcon from "./SortDescendingIcon";
import Span from "./Span";

const SelectColumn = ({rowId, tag, disableSelect, row}) => {
    const selectedRowIds = useSelector(state => state.datatable[tag].selectedRowIds);
    const dispatch = useDispatch();

    const handleCheck = () => dispatch(toggleRowSelection({tag, id: rowId}));

    return (
        <td key={`row-${rowId}-select`} scope="row" style={{minWidth: '80px'}}>
            <Checkbox
                disabled={disableSelect && disableSelect(row)}
                checked={selectedRowIds.includes(rowId)}
                id={`row-${rowId}-checkbox`}
                onClick={handleCheck}
            />
        </td>
    );
}

const Row = ({columns, rowId, tag, selectable, disableSelect, index, visibleColumns}) => {
    const row = useSelector(state => state.datatable[tag].rows[rowId]);
    const sortType = useSelector(state => state.datatable[tag].sortType);
    const selectedColumn = useSelector(state => state.datatable[tag].selectedColumn);
    const toggledRowIndex = useSelector(state => state.datatable[tag].toggledRowIndex);

    const dispatch = useDispatch();

    return (
        <>
            <tr>
                {selectable && <SelectColumn tag={tag} rowId={rowId} row={row} disableSelect={disableSelect}/>}
                {(columns.length - visibleColumns.length > 0) ? (
                    <td role="button" onClick={() => dispatch(toggleRow({tag, index}))}>
                        {toggledRowIndex === index ? <CollapseRowIcon/> : <ToggleRowIcon/>}
                    </td>
                ) : null}
                {columns.map((column, index) => (
                    <Cell
                        row={row}
                        visibleColumns={visibleColumns}
                        column={column}
                        index={index}
                        key={index}
                    />
                ))}
            </tr>
            {toggledRowIndex === index && (
                <tr key="row-collapsible">
                    <td colSpan={visibleColumns.length + (selectable ? 2 : 1)}>
                        <table className="w-100">
                            <tbody>
                            {columns
                                .filter(c => !visibleColumns.includes(c.accessor))
                                .map(({
                                          accessor,
                                          label,
                                          sortable,
                                          CustomComponent,
                                          as = null,
                                          isHTML = false,
                                          canOverflow = false
                                      }, index) => {
                                    return (
                                        <tr key={`collapse-container-${index}`}>
                                            <
                                                th
                                                role={sortable ? 'button' : ''}
                                                onClick={() => sortable ? dispatch(toggleSorting({
                                                    tag,
                                                    accessor: as ?? accessor
                                                })) : false}
                                            >
                                                {label}
                                                {sortable && selectedColumn === (as ?? accessor) ? (
                                                    <span>
                                                                    {sortType === 'asc' ? <SortAscendingIcon/> :
                                                                        <SortDescendingIcon/>}
                                                                </span>
                                                ) : null}
                                            </th>
                                            <td style={{
                                                padding: '10px 20px',
                                                overflow: canOverflow ? 'visible' : 'hidden'
                                            }}>
                                                {CustomComponent ? <CustomComponent row={row}/> :
                                                    <Span isHTML={isHTML} content={row[accessor]}
                                                          style={{whiteSpace: 'break-spaces'}}/>}
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    </td>
                </tr>
            )}
        </>


    )
}

export default memo(Row, (prevProps, nextProps) => prevProps.rowId === nextProps.rowId && (prevProps.visibleColumns.length === nextProps.visibleColumns.length) && prevProps.visibleColumns.every(el => nextProps.visibleColumns.includes(el)));
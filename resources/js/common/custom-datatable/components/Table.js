import React, {useCallback, useEffect, useLayoutEffect, useState, Fragment} from 'react';

import {useDispatch, useSelector} from "react-redux";
import {toggleSorting, updateTable} from "../../../store/slices/dataTableSlice";

import {withSize} from "react-sizeme";
import SortAscendingIcon from "./SortAscendingIcon";
import SortDescendingIcon from "./SortDescendingIcon";
import TableOptions from "./TableOptions";
import Checkbox from "./Checkbox";
import TableFooter from "./TableFooter";
import Row from './Row';

import axios from 'axios';

let cancelToken;

const Table = ({
                   columns,
                   tag,
                   variant,
                   fetchUrl,
                   rowIdentifier,
                   search,
                   selectable,
                   disableSelect,
                   size,
                   dateToFilter,
                   refresh,
                   params,
                   onPageChange,
                   offlineMode,
                   offlineRows,
                   emptyString
               }) => {
    const [visibleColumns, setVisibleColumns] = useState([]);

    const loading = useSelector(state => state.datatable[tag].loading);
    const refreshToken = useSelector(state => state.datatable[tag].refreshToken);
    const rowIds = useSelector(state => state.datatable[tag].rowIds);
    const currentPage = useSelector(state => state.datatable[tag].currentPage);
    const perPage = useSelector(state => state.datatable[tag].perPage);
    const q = useSelector(state => state.datatable[tag].q);
    const selectedColumn = useSelector(state => state.datatable[tag].selectedColumn);
    const sortType = useSelector(state => state.datatable[tag].sortType);
    const selectAll = useSelector(state => state.datatable[tag].selectAll);

    const dispatch = useDispatch();

    const handleCheckAll = () => {
        dispatch(updateTable({
            tag,
            selectAll: !selectAll,
            selectedRowIds: selectAll ? [] : rowIds
        }));
    }

    const offlineDataService = (page = 1, perPage = 10, q = '', sortType = 'asc', sortBy = null) => {
        dispatch(updateTable({tag, loading: true, toggledRowIndex: null}));
        const filteredRows = offlineRows.filter(r => {
            return Object
                .values(r)
                .filter(v => typeof v === 'string')
                .map(v => v.replace(/(<([^>]+)>)/gi, ""))
                .filter(v => v.toLowerCase().includes(q.toLowerCase()))
                .length > 0
        });

        if (sortBy) {
            filteredRows.sort((a, b) =>  {
                if(sortType === 'asc'){
                    return String(a[sortBy]).localeCompare(String(b[sortBy]))
                }
                return String(b[sortBy]).localeCompare(String(a[sortBy]));
            });
        }

        const paginatedRows = filteredRows.slice((page - 1) * perPage, page * perPage).reduce((prev, curr, index) => {
            return {...prev, [index]: curr}
        }, {});

        dispatch(updateTable({
            tag,
            rowIds: Object.keys(paginatedRows),
            rows: paginatedRows,
            loading: false,
            selectAll: false,
            selectedRowIds: [],
            totalPages: Math.ceil(filteredRows.length / parseInt(perPage)),
            stats: {
                currentPageTotal: paginatedRows.length,
                totalRecords: filteredRows.length
            },
            toggledRowIndex: null
        }))
    }

    const fetchData = () => {
        dispatch(updateTable({tag, loading: true, toggledRowIndex: null}));
        if (offlineMode) {
            return offlineDataService(currentPage, perPage, q, sortType, selectedColumn);
        }
        axiosFetch.get(fetchUrl, {
            params: {
                page: currentPage,
                per_page: perPage,
                search: q,
                sort_by: selectedColumn,
                sort_type: sortType,
                date_to_filter: dateToFilter,
                ...params
            },
            cancelToken: cancelToken?.token
        })
            .then(({data: {data}}) => {
                const results = {}
                data.data.forEach((d, i) => results[d[rowIdentifier] ?? i] = d);

                dispatch(updateTable({
                    tag,
                    rowIds: data.data.map((d, i) => d[rowIdentifier] ?? i),
                    rows: results,
                    loading: false,
                    selectAll: false,
                    selectedRowIds: [],
                    totalPages: Math.ceil(data.total / parseInt(data.per_page)),
                    stats: {
                        currentPageTotal: data.data.length,
                        totalRecords: data.total
                    },
                    toggledRowIndex: null
                }))
                if (data.data.length === 0 && currentPage > 1) {
                    dispatch(updateTable({
                        tag,
                        currentPage: 1,
                        q: '',
                        sortType: 'asc',
                        selectedColumn: null
                    }));
                }
            })
            .catch(() => {

            })
    }

    useLayoutEffect(() => {
        let width = 0;
        const visibleCols = [];
        const orderedColumns = columns.sort((a, b) => a.position - b.position);
        const orderedColumnsByPriority = [...orderedColumns].sort((a, b) => b.priority - a.priority);

        for (const column of orderedColumnsByPriority) {
            if (column.minWidth + width < (size.width - (selectable ? 180 : 100))) {
                visibleCols.push(column.accessor);
                width = width + column.minWidth;
            }
        }

        if (columns.length === visibleCols.length) {
            // close the toggled row
            dispatch(updateTable({tag, toggledRowIndex: null}));
        }

        setVisibleColumns(() => orderedColumns.filter(c => visibleCols.includes(c.accessor)).map(c => c.accessor));
    }, [size.width])

    useEffect(() => {
        fetchData();
    }, [perPage, currentPage, selectedColumn, q, sortType, refreshToken, refresh])

    const handlePerPageChange = v => dispatch(updateTable({
        tag,
        perPage: v,
        currentPage: 1,
        selectAll: false,
        selectedRowIds: []
    }));

    const handleOnInputChange = useCallback((e) => {
        const value = e.target.value;
        dispatch(updateTable({tag, q: value, currentPage: 1, selectAll: false, selectedRowIds: []}));
        if (typeof cancelToken !== typeof undefined) {
            cancelToken.cancel()
        }

        cancelToken = axios.CancelToken.source();
    }, [q])

    return (
        <div>
            <
                TableOptions
                perPage={perPage}
                onPerPageChange={handlePerPageChange}
                search={search}
                onInputChange={handleOnInputChange}
                q={q}
            />
            <table className="table mt-2 mb-1-2 AppDataTable">
                <thead className={`dt-bg-${variant}`}>
                <tr>
                    {selectable ? (
                        <th key="select-all-th" style={{minWidth: '80px'}} scope="col">
                            <Checkbox id="select-all-checkbox" checked={selectAll} onClick={handleCheckAll}/>
                        </th>
                    ) : null}
                    {(columns.length - visibleColumns.length > 0) && <th/>}
                    {columns.map(({label, sortable, accessor, minWidth, as = null}, index) => {
                        if (!visibleColumns.includes(accessor)) return <Fragment key={index}/>;
                        return (
                            <
                                th
                                key={index}
                                scope="col"
                                role={sortable ? 'button' : ''}
                                onClick={() => sortable ? dispatch(toggleSorting({
                                    tag,
                                    accessor: as ?? accessor
                                })) : false}
                                style={{minWidth}}
                            >
                                {label}
                                {sortable && selectedColumn === (as ?? accessor) ? (
                                    <span>
                                            {sortType === 'asc' ? <SortAscendingIcon/> : <SortDescendingIcon/>}
                                        </span>
                                ) : null}
                            </th>
                        )
                    })}
                </tr>
                </thead>
                <tbody>
                {rowIds.length === 0 && !loading ? (
                    <tr>
                        <td colSpan={visibleColumns.length + (selectable ? 2 : 1)} className="text-center">{emptyString}
                        </td>
                    </tr>
                ) : null}
                {rowIds.map((rowId, index) => (
                    <Row
                        visibleColumns={visibleColumns}
                        tag={tag}
                        columns={columns}
                        rowId={rowId}
                        selectable={selectable}
                        disableSelect={disableSelect}
                        index={index}
                        key={index}
                    />
                ))}
                </tbody>
            </table>
            <TableFooter tag={tag} onPageChange={onPageChange}/>
        </div>
    )
}

export default withSize()(Table);
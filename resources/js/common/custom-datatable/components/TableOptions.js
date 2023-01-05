import React from 'react';

const TableOptions = ({perPage, onPerPageChange, search, q, onInputChange}) => {
    return (
        <div className="d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
            <div className="form-group d-inline-flex align-items-center">
                <span style={{marginRight: '1rem'}}>Show</span>
                <select
                    className="form-select form-select-sm cursor-pointer form-control-sm"
                    style={{marginRight: '.4rem'}}
                    onChange={e => onPerPageChange(parseInt(e.target.value))}
                    value={perPage}
                >
                    <option value={10}>10</option>
                    <option value={25}>25</option>
                    <option value={50}>50</option>
                    <option value={100}>100</option>
                </select>
                <span>entries</span>
            </div>
            {search && (
                <div className="form-group d-inline-flex align-items-center mt-1 mt-sm-0">
                    <span>Search:&nbsp;</span>
                    <input
                        value={q}
                        onChange={onInputChange}
                        className="form-control form-control-sm"
                        type="search"
                    />
                </div>
            )}
        </div>
    )
}

export default TableOptions;
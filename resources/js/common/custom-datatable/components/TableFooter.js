import React from 'react';
import {useDispatch, useSelector} from "react-redux";
import {updateTable} from "../../../store/slices/dataTableSlice";

import ReactPaginate from "react-paginate";

const TableFooter = ({tag, onPageChange}) => {
    const stats = useSelector(state => state.datatable[tag].stats);
    const perPage = useSelector(state => state.datatable[tag].perPage);
    const currentPage = useSelector(state => state.datatable[tag].currentPage);
    const totalPages = useSelector(state => state.datatable[tag].totalPages);

    const dispatch = useDispatch();

    return(
        <div className="d-flex align-items-center justify-content-between">
            {totalPages > 1 ?
                <div className="row w-100 text-lg-start align-items-center text-center">
                    <div className="col col-12 col-lg-6 col-sm-12 mb-1 mb-3 mb-lg-0">
                            <span
                                id="datatable__info">Showing {(currentPage - 1) * perPage + 1} to {(currentPage - 1) * perPage + stats.currentPageTotal} of {stats.totalRecords} entries</span>
                    </div>
                    <div className="col col-12 col-lg-6 col-sm-12">
                        <ReactPaginate
                            className="react-pagination pagination pagination-rounded justify-content-center justify-content-lg-end"
                            nextLabel="&raquo;"
                            onPageChange={({selected}) => {
                                dispatch(updateTable({tag, currentPage: selected + 1}))
                                typeof onPageChange === 'function' && onPageChange();
                            }}
                            marginPagesDisplayed={1}
                            pageCount={totalPages}
                            previousLabel="&laquo;"
                            pageClassName="page-item"
                            pageLinkClassName="page-link"
                            previousClassName="page-item"
                            previousLinkClassName="page-link"
                            nextClassName="page-item"
                            nextLinkClassName="page-link"
                            breakLabel="..."
                            breakClassName="page-item"
                            breakLinkClassName="page-link"
                            containerClassName="pagination"
                            activeClassName="active"
                            renderOnZeroPageCount={null}
                            forcePage={currentPage - 1}
                        />
                    </div>
                </div>
                : null}
        </div>
    )
}

export default TableFooter;